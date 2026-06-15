<?php

namespace App\Services\AI;

use App\Enums\AI\AiRunStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiIdempotencyCoordinator
{
    /**
     * @return array{visitor_message:Message,request_uuid:string,replay:?array{reply:Message}}
     */
    public function resolveVisitorMessage(
        Tenant $tenant,
        Conversation $conversation,
        string $body,
        ?string $clientRequestId,
    ): array {
        $clientRequestId = $clientRequestId !== null && Str::isUuid($clientRequestId)
            ? $clientRequestId
            : null;

        return DB::transaction(function () use ($tenant, $conversation, $body, $clientRequestId): array {
            if ($clientRequestId !== null) {
                $existingVisitor = Message::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('conversation_id', $conversation->id)
                    ->where('request_uuid', $clientRequestId)
                    ->where('role', MessageRole::Visitor->value)
                    ->lockForUpdate()
                    ->first();

                if ($existingVisitor !== null) {
                    $replay = $this->resolveSuccessfulReplay($tenant, $clientRequestId, $existingVisitor);

                    return [
                        'visitor_message' => $existingVisitor,
                        'request_uuid' => $clientRequestId,
                        'replay' => $replay,
                    ];
                }
            }

            $visitorMessage = Message::query()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::Visitor->value,
                'body' => $body,
                'request_uuid' => $clientRequestId,
            ]);

            $requestUuid = $clientRequestId ?? $visitorMessage->uuid;

            if ($visitorMessage->request_uuid !== $requestUuid) {
                $visitorMessage->update(['request_uuid' => $requestUuid]);
            }

            return [
                'visitor_message' => $visitorMessage->fresh(),
                'request_uuid' => $requestUuid,
                'replay' => null,
            ];
        });
    }

    /**
     * @return array{run:AiRun,replay:?array{content:string,run:AiRun}}
     */
    public function claimRun(
        Tenant $tenant,
        Conversation $conversation,
        Message $triggeringMessage,
        string $requestUuid,
        string $provider,
        string $model,
        ?string $credentialSource,
    ): array {
        try {
            return DB::transaction(function () use (
                $tenant,
                $conversation,
                $triggeringMessage,
                $requestUuid,
                $provider,
                $model,
                $credentialSource,
            ): array {
                $existing = AiRun::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('request_uuid', $requestUuid)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->status === AiRunStatus::Success->value && $existing->message !== null) {
                        return [
                            'run' => $existing,
                            'replay' => [
                                'content' => (string) $existing->message->body,
                                'run' => $existing,
                            ],
                        ];
                    }

                    if ($existing->status === AiRunStatus::Processing->value) {
                        return [
                            'run' => $existing,
                            'replay' => $this->waitForProcessingCompletion($existing),
                        ];
                    }

                    $existing->update([
                        'status' => AiRunStatus::Processing->value,
                        'error_category' => null,
                        'message_id' => null,
                        'input_tokens' => null,
                        'output_tokens' => null,
                        'total_tokens' => null,
                        'latency_ms' => null,
                        'attempt_number' => (int) $existing->attempt_number + 1,
                        'provider' => $provider,
                        'model' => $model,
                        'credential_source' => $credentialSource,
                        'triggering_message_id' => $triggeringMessage->id,
                        'conversation_id' => $conversation->id,
                    ]);

                    return ['run' => $existing->fresh(), 'replay' => null];
                }

                $successfulForTrigger = AiRun::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('triggering_message_id', $triggeringMessage->id)
                    ->where('status', AiRunStatus::Success->value)
                    ->whereNotNull('message_id')
                    ->lockForUpdate()
                    ->first();

                if ($successfulForTrigger !== null) {
                    return [
                        'run' => $successfulForTrigger,
                        'replay' => [
                            'content' => (string) $successfulForTrigger->message?->body,
                            'run' => $successfulForTrigger,
                        ],
                    ];
                }

                $run = AiRun::query()->create([
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversation->id,
                    'triggering_message_id' => $triggeringMessage->id,
                    'request_uuid' => $requestUuid,
                    'provider' => $provider,
                    'model' => $model,
                    'credential_source' => $credentialSource,
                    'status' => AiRunStatus::Processing->value,
                    'attempt_number' => 1,
                ]);

                return ['run' => $run, 'replay' => null];
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = AiRun::query()
                ->where('tenant_id', $tenant->id)
                ->where('request_uuid', $requestUuid)
                ->firstOrFail();

            return $this->claimRun(
                $tenant,
                $conversation,
                $triggeringMessage,
                $requestUuid,
                $provider,
                $model,
                $credentialSource,
            );
        }
    }

    /**
     * @return array{assistant:Message,run:AiRun}
     */
    public function finalizeSuccess(AiRun $run, string $content): array
    {
        return DB::transaction(function () use ($run, $content): array {
            $lockedRun = AiRun::query()
                ->whereKey($run->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->status === AiRunStatus::Success->value && $lockedRun->message !== null) {
                return [
                    'assistant' => $lockedRun->message,
                    'run' => $lockedRun,
                ];
            }

            $duplicateSuccess = AiRun::query()
                ->where('tenant_id', $lockedRun->tenant_id)
                ->where('triggering_message_id', $lockedRun->triggering_message_id)
                ->where('status', AiRunStatus::Success->value)
                ->whereNotNull('message_id')
                ->where('id', '!=', $lockedRun->id)
                ->lockForUpdate()
                ->first();

            if ($duplicateSuccess?->message !== null) {
                $lockedRun->update([
                    'status' => AiRunStatus::Failed->value,
                    'error_category' => 'internal',
                ]);

                return [
                    'assistant' => $duplicateSuccess->message,
                    'run' => $duplicateSuccess,
                ];
            }

            $assistant = Message::query()->create([
                'tenant_id' => $lockedRun->tenant_id,
                'conversation_id' => $lockedRun->conversation_id,
                'role' => MessageRole::Assistant->value,
                'body' => $content,
            ]);

            $lockedRun->update(['message_id' => $assistant->id, 'status' => AiRunStatus::Success->value]);

            return [
                'assistant' => $assistant,
                'run' => $lockedRun->fresh(['message']),
            ];
        });
    }

    /**
     * @return array{reply:Message}|null
     */
    private function resolveSuccessfulReplay(Tenant $tenant, string $requestUuid, Message $visitorMessage): ?array
    {
        $run = AiRun::query()
            ->where('tenant_id', $tenant->id)
            ->where('request_uuid', $requestUuid)
            ->where('status', AiRunStatus::Success->value)
            ->whereNotNull('message_id')
            ->with('message')
            ->lockForUpdate()
            ->first();

        if ($run?->message !== null) {
            return ['reply' => $run->message];
        }

        $run = AiRun::query()
            ->where('tenant_id', $tenant->id)
            ->where('triggering_message_id', $visitorMessage->id)
            ->where('status', AiRunStatus::Success->value)
            ->whereNotNull('message_id')
            ->with('message')
            ->lockForUpdate()
            ->first();

        if ($run?->message !== null) {
            return ['reply' => $run->message];
        }

        return null;
    }

    /**
     * @return array{content:string,run:AiRun}|null
     */
    private function waitForProcessingCompletion(AiRun $run): ?array
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            usleep(50_000);
            $fresh = AiRun::query()->whereKey($run->id)->with('message')->first();

            if ($fresh === null) {
                return null;
            }

            if ($fresh->status === AiRunStatus::Success->value && $fresh->message !== null) {
                return [
                    'content' => (string) $fresh->message->body,
                    'run' => $fresh,
                ];
            }

            if ($fresh->status === AiRunStatus::Failed->value) {
                return null;
            }
        }

        return null;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
