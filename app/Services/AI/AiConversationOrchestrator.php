<?php

namespace App\Services\AI;

use App\Data\AI\AiRequest;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiContentPolicyException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Exceptions\AI\AiTimeoutException;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Support\Str;

class AiConversationOrchestrator
{
    public function __construct(
        private readonly TenantAiConfigService $configService,
        private readonly AiProviderRegistry $providers,
        private readonly AiPromptBuilder $promptBuilder,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $knowledge
     * @return array{status:string,content:?string,error_category:?string,run:AiRun}
     */
    public function respond(
        Tenant $tenant,
        Conversation $conversation,
        string $visitorMessage,
        array $knowledge,
        ?string $requestId = null,
    ): array {
        $requestId = $requestId && Str::isUuid($requestId) ? $requestId : (string) Str::uuid();

        $existing = AiRun::query()->where('request_uuid', $requestId)->first();
        if ($existing !== null && $existing->status === 'success' && $existing->message !== null) {
            return [
                'status' => 'success',
                'content' => (string) $existing->message->body,
                'error_category' => null,
                'run' => $existing,
            ];
        }

        $effective = $this->configService->getEffectiveConfig($tenant);

        $run = AiRun::query()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'request_uuid' => $requestId,
            'provider' => $effective['provider'],
            'model' => $effective['model'],
            'status' => 'processing',
        ]);

        $settings = TenantSettings::query()->first();
        $messages = $this->promptBuilder->build($tenant, $settings, $conversation, $visitorMessage, $knowledge);

        $request = new AiRequest(
            provider: $effective['provider'],
            model: $effective['model'],
            messages: $messages,
            temperature: (float) $effective['temperature'],
            maxOutputTokens: (int) $effective['max_output_tokens'],
            timeoutSeconds: (int) $effective['timeout_seconds'],
            requestId: $requestId,
            apiKey: $effective['api_key'] ?? null,
        );

        try {
            $response = $this->providers->resolve($effective['provider'])->chat($request);

            $run->update([
                'status' => 'success',
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'total_tokens' => $response->usage->totalTokens,
                'latency_ms' => $response->usage->latencyMs,
            ]);

            return [
                'status' => 'success',
                'content' => Str::limit(strip_tags($response->content), (int) config('ai.max_output_chars', 3000), ''),
                'error_category' => null,
                'run' => $run->fresh(),
            ];
        } catch (AiAuthenticationException $e) {
            return $this->fail($run, 'auth');
        } catch (AiRateLimitException $e) {
            return $this->fail($run, 'rate_limit');
        } catch (AiTimeoutException $e) {
            return $this->fail($run, 'timeout');
        } catch (AiContentPolicyException $e) {
            return $this->fail($run, 'content_policy');
        } catch (AiProviderException $e) {
            return $this->fail($run, 'provider_error');
        } catch (\Throwable $e) {
            return $this->fail($run, 'internal');
        }
    }

    /**
     * @return array{status:string,content:null,error_category:string,run:AiRun}
     */
    private function fail(AiRun $run, string $category): array
    {
        $run->update([
            'status' => 'failed',
            'error_category' => $category,
        ]);

        return [
            'status' => 'failed',
            'content' => null,
            'error_category' => $category,
            'run' => $run->fresh(),
        ];
    }
}
