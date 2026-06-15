<?php

namespace App\Services\Billing;

use App\Data\Billing\EntitlementResult;
use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Tenant;

class WidgetEntitlementService
{
    public function __construct(private readonly EntitlementResolver $resolver) {}

    /**
     * @return array{available: bool, mode: string, message: string, code: string}
     */
    public function widgetAvailability(Tenant $tenant): array
    {
        if (! $tenant->allowsTenantAccess()) {
            return [
                'available' => false,
                'mode' => 'unavailable',
                'message' => config('subscriptions.widget_unavailable_message'),
                'code' => 'service_unavailable',
            ];
        }

        $result = $this->resolver->check($tenant, PlanFeature::Widget);

        if ($result->outcome === EntitlementOutcome::SubscriptionExpired
            && $result->message === 'lead_capture_only') {
            return [
                'available' => true,
                'mode' => 'lead_capture_only',
                'message' => config('subscriptions.widget_lead_capture_only_message'),
                'code' => 'lead_capture_only',
            ];
        }

        if (! $result->isAllowed()) {
            return [
                'available' => false,
                'mode' => 'unavailable',
                'message' => config('subscriptions.widget_unavailable_message'),
                'code' => $result->outcome->safeWidgetCode(),
            ];
        }

        return [
            'available' => true,
            'mode' => 'full',
            'message' => '',
            'code' => 'ok',
        ];
    }

    public function canUseAi(Tenant $tenant): EntitlementResult
    {
        $widget = $this->widgetAvailability($tenant);

        if (! $widget['available'] || $widget['mode'] === 'lead_capture_only') {
            return new EntitlementResult(
                outcome: EntitlementOutcome::FeatureNotIncluded,
                feature: PlanFeature::AiResponses,
            );
        }

        return $this->resolver->check($tenant, PlanFeature::AiResponses);
    }

    public function canRequestHandoff(Tenant $tenant): EntitlementResult
    {
        $status = $this->resolver->effectiveSubscriptionStatus($tenant);

        if ($status !== null && in_array($status, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue], true)) {
            if (config('subscriptions.human_conversation_continuity_on_expiry', true)) {
                return new EntitlementResult(
                    outcome: EntitlementOutcome::Denied,
                    feature: PlanFeature::HumanHandoff,
                    message: 'New human handoff requests are unavailable.',
                );
            }
        }

        return $this->resolver->check($tenant, PlanFeature::HumanHandoff);
    }

    public function canPollHumanConversation(Tenant $tenant): bool
    {
        if (! $tenant->allowsTenantAccess()) {
            return false;
        }

        if (! config('subscriptions.human_conversation_continuity_on_expiry', true)) {
            return $this->resolver->check($tenant, PlanFeature::HumanHandoff)->isAllowed();
        }

        return true;
    }
}
