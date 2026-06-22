<?php

namespace App\Services\Widget;

use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;

class HandoffPromotionService
{
    /**
     * @return array{prominent: bool, reason: ?string}
     */
    public function evaluate(
        Conversation $conversation,
        string $visitorMessage,
        ?Message $reply,
        array $knowledge,
    ): array {
        if ($conversation->mode === ConversationMode::HandoffRequested
            || $conversation->mode === ConversationMode::Human) {
            return ['prominent' => true, 'reason' => 'conversation_handoff'];
        }

        if ($this->explicitHumanRequest($visitorMessage)) {
            return ['prominent' => true, 'reason' => 'explicit_human_request'];
        }

        if ($this->phoneWithNextStep($visitorMessage)) {
            return ['prominent' => true, 'reason' => 'phone_with_next_step'];
        }

        if ($this->isHighRisk($visitorMessage)) {
            return ['prominent' => true, 'reason' => 'high_risk'];
        }

        if ($knowledge === [] && $this->repeatedFallbackCount($conversation) >= 2) {
            return ['prominent' => true, 'reason' => 'repeated_fallback'];
        }

        if ($reply !== null && $this->isAiFallbackReply($reply)) {
            $fallbackCount = $this->repeatedFallbackCount($conversation) + 1;
            if ($fallbackCount >= 2) {
                return ['prominent' => true, 'reason' => 'repeated_fallback'];
            }
        }

        return ['prominent' => false, 'reason' => null];
    }

    private function explicitHumanRequest(string $message): bool
    {
        $patterns = [
            '/\b(?:talk|speak|chat|connect)\s+(?:to|with)\s+(?:a\s+)?(?:human|counsell?or|agent|person|staff|representative)\b/i',
            '/\b(?:human|real)\s+(?:counsell?or|agent|person|help|support)\b/i',
            '/\b(?:call\s*me|callback|call\s*back|whatsapp|contact\s*me)\b/i',
            '/\b(?:i\s+)?(?:want|need|prefer)\s+(?:a\s+)?(?:human|counsell?or|agent|person)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function phoneWithNextStep(string $message): bool
    {
        $hasPhone = (bool) preg_match('/(?:\+91|91)?[6-9]\d{9}/', preg_replace('/\s+/', '', $message) ?? $message);
        $wantsNextStep = (bool) preg_match(
            '/\b(?:call|contact|callback|next\s+step|admission|apply|enrol|enroll|guide\s+me|help\s+me)\b/i',
            $message,
        );

        return $hasPhone && $wantsNextStep;
    }

    private function isHighRisk(string $message): bool
    {
        $patterns = [
            '/\b(?:payment\s+fraud|fraudulent|scam|cheated)\b/i',
            '/\b(?:legal|lawyer|court|sue|lawsuit)\b/i',
            '/\b(?:complaint|grievance|consumer\s+forum)\b/i',
            '/\b(?:urgent|asap|immediately)\s+(?:admission|payment|fee|deposit)\b/i',
            '/\b(?:document\s+verif|verify\s+my\s+documents?)\b/i',
            '/\b(?:guarantee|guaranteed|100\s*%\s*(?:admission|visa|approval))\b/i',
            '/\b(?:exact\s+(?:fee|cost|commitment)|confirm\s+(?:my\s+)?admission)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function repeatedFallbackCount(Conversation $conversation): int
    {
        return $conversation->messages()
            ->where('role', MessageRole::System->value)
            ->where(function ($query): void {
                $query->where('body', 'like', '%temporarily unavailable%')
                    ->orWhere('body', 'like', '%try again shortly%')
                    ->orWhere('body', 'like', '%contact our team%');
            })
            ->count();
    }

    private function isAiFallbackReply(Message $reply): bool
    {
        if ($reply->role !== MessageRole::System) {
            return false;
        }

        $body = strtolower((string) $reply->body);

        return str_contains($body, 'temporarily unavailable')
            || str_contains($body, 'try again shortly')
            || str_contains($body, 'contact our team');
    }
}
