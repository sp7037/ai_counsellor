<?php

namespace App\Services\AI;

use App\Data\AI\AiMessage;
use App\Data\AI\AiRequest;
use App\Enums\AI\AiErrorCategory;
use App\Enums\AI\AiRunStatus;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiContentPolicyException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Exceptions\AI\AiTimeoutException;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantSettings;

class AiConversationOrchestrator
{
    public function __construct(
        private readonly TenantAiConfigService $configService,
        private readonly AiProviderRegistry $providers,
        private readonly AiPromptBuilder $promptBuilder,
        private readonly AiIdempotencyCoordinator $idempotency,
        private readonly SafeAiExceptionMapper $exceptionMapper,
        private readonly AiReplyCompletionGuard $completionGuard,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $knowledge
     * @return array{status:string,content:?string,error_category:?string,run:AiRun,replay:bool}
     */
    public function respond(
        Tenant $tenant,
        Conversation $conversation,
        Message $triggeringMessage,
        string $visitorMessage,
        array $knowledge,
        string $requestUuid,
    ): array {
        $this->assertTriggeringMessageBelongsToConversation($tenant, $conversation, $triggeringMessage);

        try {
            $effective = $this->configService->getEffectiveConfig($tenant);
        } catch (AiProviderException $exception) {
            $run = $this->createImmediateFailedRun(
                $tenant,
                $conversation,
                $triggeringMessage,
                $requestUuid,
                $this->resolveFailureProvider($tenant),
                $this->resolveFailureModel($tenant),
                $this->mapConfigException($exception),
            );

            return [
                'status' => 'failed',
                'content' => null,
                'error_category' => $run->error_category,
                'run' => $run,
                'replay' => false,
            ];
        }

        $claim = $this->idempotency->claimRun(
            tenant: $tenant,
            conversation: $conversation,
            triggeringMessage: $triggeringMessage,
            requestUuid: $requestUuid,
            provider: $effective['provider'],
            model: $effective['model'],
            credentialSource: $effective['credential_source']->value,
        );

        if ($claim['replay'] !== null) {
            return [
                'status' => 'success',
                'content' => $claim['replay']['content'],
                'error_category' => null,
                'run' => $claim['replay']['run'],
                'replay' => true,
            ];
        }

        /** @var AiRun $run */
        $run = $claim['run'];

        $settings = TenantSettings::query()->first();
        $messages = $this->promptBuilder->build($tenant, $settings, $conversation, $visitorMessage, $knowledge);

        $request = new AiRequest(
            provider: $effective['provider'],
            model: $effective['model'],
            messages: $messages,
            temperature: (float) $effective['temperature'],
            maxOutputTokens: $this->resolveMaxOutputTokens((int) $effective['max_output_tokens']),
            timeoutSeconds: (int) $effective['timeout_seconds'],
            requestId: $requestUuid,
            apiKey: $effective['api_key'] ?? null,
        );

        try {
            $provider = $this->providers->resolve($effective['provider']);
            $preferredFollowUp = $this->completionGuard->extractPreferredFollowUp($messages);
            $response = $provider->chat($request);

            if ($this->completionGuard->shouldRetryForTruncation($response->content, $response->finishReason)) {
                $retryRequest = new AiRequest(
                    provider: $request->provider,
                    model: $request->model,
                    messages: $this->messagesWithRetryInstruction($messages),
                    temperature: $request->temperature,
                    maxOutputTokens: max(
                        $request->maxOutputTokens,
                        (int) config('ai.recommended_counselling_output_tokens', 320),
                    ),
                    timeoutSeconds: $request->timeoutSeconds,
                    requestId: $request->requestId.'-retry',
                    apiKey: $request->apiKey,
                );

                $retryResponse = $provider->chat($retryRequest);

                if (! $this->completionGuard->looksIncomplete($retryResponse->content, $retryResponse->finishReason)) {
                    $response = $retryResponse;
                }
            }

            $run->update([
                'input_tokens' => $this->normalizeTokenCount($response->usage->inputTokens),
                'output_tokens' => $this->normalizeTokenCount($response->usage->outputTokens),
                'total_tokens' => $this->normalizeTokenCount($response->usage->totalTokens),
                'latency_ms' => max(0, (int) $response->usage->latencyMs),
            ]);

            $content = $this->completionGuard->finalize(
                $response->content,
                $response->finishReason,
                $preferredFollowUp,
            );
            $finalized = $this->idempotency->finalizeSuccess($run, $content);

            return [
                'status' => 'success',
                'content' => $content,
                'error_category' => null,
                'run' => $finalized['run'],
                'replay' => false,
            ];
        } catch (AiAuthenticationException $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, AiErrorCategory::Auth);
        } catch (AiRateLimitException $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, AiErrorCategory::RateLimit);
        } catch (AiTimeoutException $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, AiErrorCategory::Timeout);
        } catch (AiContentPolicyException $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, AiErrorCategory::ContentPolicy);
        } catch (AiProviderException $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, $this->mapConfigException($exception));
        } catch (\Throwable $exception) {
            $this->exceptionMapper->logProviderFailure($effective['provider'], $exception, $requestUuid);

            return $this->fail($run, AiErrorCategory::Internal);
        }
    }

    /**
     * @return array{status:string,content:null,error_category:string,run:AiRun,replay:bool}
     */
    private function fail(AiRun $run, AiErrorCategory $category): array
    {
        $run->update([
            'status' => AiRunStatus::Failed->value,
            'error_category' => $category->value,
            'message_id' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
        ]);

        return [
            'status' => 'failed',
            'content' => null,
            'error_category' => $category->value,
            'run' => $run->fresh(),
            'replay' => false,
        ];
    }

    private function createImmediateFailedRun(
        Tenant $tenant,
        Conversation $conversation,
        Message $triggeringMessage,
        string $requestUuid,
        string $provider,
        string $model,
        AiErrorCategory $category,
    ): AiRun {
        $claim = $this->idempotency->claimRun(
            tenant: $tenant,
            conversation: $conversation,
            triggeringMessage: $triggeringMessage,
            requestUuid: $requestUuid,
            provider: $provider,
            model: $model,
            credentialSource: null,
        );

        $run = $claim['run'];
        $run->update([
            'status' => AiRunStatus::Failed->value,
            'error_category' => $category->value,
        ]);

        return $run->fresh();
    }

    private function mapConfigException(AiProviderException $exception): AiErrorCategory
    {
        return match ($exception->getMessage()) {
            'Tenant AI configuration is disabled.' => AiErrorCategory::Disabled,
            'Tenant provider API key is required for the selected credential mode.' => AiErrorCategory::MissingKey,
            'AI provider is disabled.' => AiErrorCategory::Disabled,
            default => AiErrorCategory::ProviderError,
        };
    }

    private function assertTriggeringMessageBelongsToConversation(
        Tenant $tenant,
        Conversation $conversation,
        Message $triggeringMessage,
    ): void {
        if (
            $triggeringMessage->tenant_id !== $tenant->id
            || $triggeringMessage->conversation_id !== $conversation->id
        ) {
            throw new AiProviderException('Triggering message does not belong to the active conversation.');
        }
    }

    private function resolveFailureProvider(Tenant $tenant): string
    {
        return (string) config('ai.default_provider', 'openai');
    }

    private function resolveFailureModel(Tenant $tenant): string
    {
        $provider = $this->resolveFailureProvider($tenant);

        return (string) config('ai.providers.'.$provider.'.model', 'gpt-4o-mini');
    }

    private function normalizeTokenCount(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value < 0 || $value > 1_000_000) {
            return null;
        }

        return $value;
    }

    private function resolveMaxOutputTokens(int $configured): int
    {
        $minimum = (int) config('ai.min_output_tokens', 240);
        $limit = (int) config('ai.max_output_tokens_limit', 1200);

        return max($minimum, min($limit, $configured));
    }

    /**
     * @param  array<AiMessage>  $messages
     * @return array<AiMessage>
     */
    private function messagesWithRetryInstruction(array $messages): array
    {
        if ($messages === []) {
            return [new AiMessage('system', $this->completionGuard->retryInstruction())];
        }

        $lastIndex = array_key_last($messages);
        $lastMessage = $messages[$lastIndex];

        if ($lastMessage->role === 'user') {
            return [
                ...array_slice($messages, 0, $lastIndex),
                new AiMessage('system', $this->completionGuard->retryInstruction()),
                $lastMessage,
            ];
        }

        return [
            ...$messages,
            new AiMessage('system', $this->completionGuard->retryInstruction()),
        ];
    }
}
