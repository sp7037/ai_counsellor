<?php

namespace App\Enums\Billing;

enum PlanFeature: string
{
    case Widget = 'widget';
    case AiResponses = 'ai_responses';
    case KnowledgeBase = 'knowledge_base';
    case LeadManagement = 'lead_management';
    case CounsellorWorkspace = 'counsellor_workspace';
    case HumanHandoff = 'human_handoff';
    case UsageReporting = 'usage_reporting';
    case CustomAiCredentials = 'custom_ai_credentials';
    case PlatformCredentialFallback = 'platform_credential_fallback';
    case DataExport = 'data_export';
    case ApiAccess = 'api_access';
    case AdvancedQualification = 'advanced_qualification';
    case SuggestedReplies = 'suggested_replies';
    case WhatsAppIntegration = 'whatsapp_integration';

    public function label(): string
    {
        return match ($this) {
            self::Widget => 'Chat widget',
            self::AiResponses => 'AI responses',
            self::KnowledgeBase => 'Knowledge base',
            self::LeadManagement => 'Lead management',
            self::CounsellorWorkspace => 'Counsellor workspace',
            self::HumanHandoff => 'Human handoff',
            self::UsageReporting => 'Usage reporting',
            self::CustomAiCredentials => 'Custom AI credentials',
            self::PlatformCredentialFallback => 'Platform credential fallback',
            self::DataExport => 'Data export',
            self::ApiAccess => 'API access',
            self::AdvancedQualification => 'Advanced qualification',
            self::SuggestedReplies => 'Suggested replies',
            self::WhatsAppIntegration => 'WhatsApp integration',
        };
    }

    public function limitMetric(): ?UsageMetric
    {
        return match ($this) {
            self::AiResponses => UsageMetric::AiRuns,
            self::KnowledgeBase => UsageMetric::KnowledgeItems,
            self::LeadManagement => UsageMetric::LeadsCreated,
            self::CounsellorWorkspace => UsageMetric::ActiveCounsellors,
            self::HumanHandoff => UsageMetric::ActiveHumanConversations,
            default => null,
        };
    }
}
