<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeAiExceptionMapper
{
    public function logProviderFailure(string $provider, Throwable $exception, ?string $requestId = null): void
    {
        Log::warning('ai.provider_failure', [
            'provider' => $provider,
            'request_id' => $requestId,
            'exception_class' => $exception::class,
            'message' => $this->safeMessage($exception),
        ]);
    }

    public function safeMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return $this->redactSecrets($message);
    }

    public function redactSecrets(string $value): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [REDACTED]',
            '/sk-[A-Za-z0-9_-]+/i' => 'sk-[REDACTED]',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\s]+/i' => 'api_key=[REDACTED]',
            '/Authorization:\s*[^\s]+/i' => 'Authorization: [REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }
}
