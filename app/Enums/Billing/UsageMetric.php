<?php

namespace App\Enums\Billing;

enum UsageMetric: string
{
    case AiRuns = 'ai_runs';
    case AiTokens = 'ai_tokens';
    case KnowledgeItems = 'knowledge_items';
    case LeadsCreated = 'leads_created';
    case ActiveCounsellors = 'active_counsellors';
    case ActiveHumanConversations = 'active_human_conversations';

    public function label(): string
    {
        return match ($this) {
            self::AiRuns => 'AI responses',
            self::AiTokens => 'AI tokens',
            self::KnowledgeItems => 'Knowledge items',
            self::LeadsCreated => 'Leads created',
            self::ActiveCounsellors => 'Active counsellors',
            self::ActiveHumanConversations => 'Active human conversations',
        };
    }

    public function periodType(): LimitPeriod
    {
        return match ($this) {
            self::KnowledgeItems, self::ActiveCounsellors => LimitPeriod::Total,
            default => LimitPeriod::BillingPeriod,
        };
    }
}
