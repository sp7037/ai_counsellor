<?php

namespace Database\Seeders;

use App\Enums\Billing\LimitPeriod;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'trial' => [
                'name' => 'Trial',
                'description' => 'Limited trial for evaluation.',
                'display_order' => 1,
                'features' => [
                    [PlanFeature::Widget, true, null],
                    [PlanFeature::AiResponses, true, 100],
                    [PlanFeature::KnowledgeBase, true, 20],
                    [PlanFeature::LeadManagement, true, 50],
                    [PlanFeature::CounsellorWorkspace, true, 1],
                    [PlanFeature::HumanHandoff, true, 2],
                ],
            ],
            'starter' => [
                'name' => 'Starter',
                'description' => 'Core widget and lead management.',
                'display_order' => 2,
                'features' => [
                    [PlanFeature::Widget, true, null],
                    [PlanFeature::AiResponses, true, 500],
                    [PlanFeature::KnowledgeBase, true, 100],
                    [PlanFeature::LeadManagement, true, 200],
                    [PlanFeature::CounsellorWorkspace, true, 2],
                    [PlanFeature::HumanHandoff, false, null],
                ],
            ],
            'professional' => [
                'name' => 'Professional',
                'description' => 'Higher limits with human handoff and usage reporting.',
                'display_order' => 3,
                'features' => [
                    [PlanFeature::Widget, true, null],
                    [PlanFeature::AiResponses, true, 2000],
                    [PlanFeature::KnowledgeBase, true, 500],
                    [PlanFeature::LeadManagement, true, 1000],
                    [PlanFeature::CounsellorWorkspace, true, 10],
                    [PlanFeature::HumanHandoff, true, 20],
                    [PlanFeature::UsageReporting, true, null],
                    [PlanFeature::CustomAiCredentials, true, null],
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'Custom limits and platform-managed options.',
                'display_order' => 4,
                'features' => [
                    [PlanFeature::Widget, true, null],
                    [PlanFeature::AiResponses, true, null],
                    [PlanFeature::KnowledgeBase, true, null],
                    [PlanFeature::LeadManagement, true, null],
                    [PlanFeature::CounsellorWorkspace, true, null],
                    [PlanFeature::HumanHandoff, true, null],
                    [PlanFeature::UsageReporting, true, null],
                    [PlanFeature::CustomAiCredentials, true, null],
                    [PlanFeature::PlatformCredentialFallback, true, null],
                    [PlanFeature::DataExport, true, null],
                ],
            ],
        ];

        foreach ($definitions as $code => $definition) {
            $plan = Plan::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'billing_interval' => 'monthly',
                    'display_order' => $definition['display_order'],
                    'is_public' => true,
                    'status' => PlanStatus::Active->value,
                ],
            );

            if ($plan->entitlements()->exists()) {
                continue;
            }

            foreach ($definition['features'] as [$feature, $enabled, $limit]) {
                PlanEntitlement::query()->create([
                    'plan_id' => $plan->id,
                    'feature' => $feature->value,
                    'enabled' => $enabled,
                    'limit_value' => $limit,
                    'limit_period' => $feature->limitMetric()?->periodType()->value ?? LimitPeriod::BillingPeriod->value,
                ]);
            }
        }
    }
}
