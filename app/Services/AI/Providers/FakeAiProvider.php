<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProviderContract;
use App\Data\AI\AiRequest;
use App\Data\AI\AiResponse;
use App\Data\AI\AiUsage;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiContentPolicyException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Exceptions\AI\AiTimeoutException;
use Illuminate\Support\Facades\Cache;

class FakeAiProvider implements AiProviderContract
{
    public function provider(): string
    {
        return 'fake';
    }

    public function supportsTools(): bool
    {
        return false;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $content = strtolower($request->messages[array_key_last($request->messages)]->content ?? '');
        $systemText = strtolower(implode("\n", array_map(
            fn ($message) => $message->content,
            array_filter($request->messages, fn ($message) => $message->role === 'system'),
        )));

        if (str_contains($content, 'trigger timeout')) {
            throw new AiTimeoutException('Fake timeout');
        }

        if (str_contains($content, 'trigger auth')) {
            throw new AiAuthenticationException('Fake authentication failure');
        }

        if (str_contains($content, 'trigger rate limit')) {
            throw new AiRateLimitException('Fake rate limit');
        }

        if (str_contains($content, 'trigger content policy')) {
            throw new AiContentPolicyException('Fake content policy refusal');
        }

        if (str_contains($content, 'trigger malformed')) {
            throw new AiProviderException('Fake malformed provider response');
        }

        if (str_contains($content, 'trigger fail-once')) {
            $cacheKey = 'fake_fail_once_'.$request->requestId;

            if (! Cache::get($cacheKey)) {
                Cache::put($cacheKey, true, 60);

                throw new AiTimeoutException('Fake first-attempt timeout');
            }
        }

        $latestUserMessage = $request->messages[array_key_last($request->messages)]->content ?? '';

        if (
            str_contains($content, 'mbbs')
            && str_contains($systemText, 'counselling flow')
        ) {
            $answer = str_contains($systemText, '[faq]') || str_contains($systemText, 'knowledge references')
                ? 'Yes, we can guide you for MBBS abroad based on our published guidance.'
                : 'Yes, I can guide you generally on MBBS abroad options. Specific fee and university details need verified information from our team.';

            $followUp = $this->extractPreferredFollowUp($systemText)
                ?? 'What is your NEET status or score?';

            return new AiResponse(
                provider: 'fake',
                model: $request->model,
                content: $answer.' '.$followUp,
                usage: new AiUsage(inputTokens: 10, outputTokens: 8, totalTokens: 18, latencyMs: 20),
            );
        }

        return new AiResponse(
            provider: 'fake',
            model: $request->model,
            content: 'AI reply: '.$latestUserMessage,
            usage: new AiUsage(inputTokens: 10, outputTokens: 8, totalTokens: 18, latencyMs: 20),
        );
    }

    private function extractPreferredFollowUp(string $systemText): ?string
    {
        if (preg_match('/preferred next follow-up(?: \(ask only this one question\))?:\s*(.+?)(?:\n|$)/i', $systemText, $matches)) {
            $question = trim($matches[1]);

            if ($question === '' || str_contains(strtolower($question), 'no follow-up question needed')) {
                return null;
            }

            return str_ends_with($question, '?') ? $question : $question.'?';
        }

        return null;
    }
}
