<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\TaxTreatment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanPricingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePricing(Plan $plan, array $data, User $actor): Plan
    {
        return DB::transaction(function () use ($plan, $data, $actor): Plan {
            $previous = [
                'currency' => $plan->currency,
                'amount_minor' => $plan->amount_minor,
                'is_purchasable' => $plan->is_purchasable,
            ];

            $amountMinor = array_key_exists('amount_minor', $data)
                ? ($data['amount_minor'] === null || $data['amount_minor'] === '' ? null : (int) $data['amount_minor'])
                : $plan->amount_minor;

            $currency = array_key_exists('currency', $data)
                ? ($data['currency'] === null || $data['currency'] === '' ? null : strtoupper((string) $data['currency']))
                : $plan->currency;

            $isPurchasable = (bool) ($data['is_purchasable'] ?? $plan->is_purchasable);

            if ($isPurchasable && ($amountMinor === null || $amountMinor <= 0 || $currency === null)) {
                throw ValidationException::withMessages([
                    'pricing' => 'Purchasable plans require a positive amount and currency.',
                ]);
            }

            $plan->update([
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'billing_interval_count' => (int) ($data['billing_interval_count'] ?? $plan->billing_interval_count ?? 1),
                'tax_treatment' => isset($data['tax_treatment']) && $data['tax_treatment'] !== ''
                    ? TaxTreatment::from($data['tax_treatment'])->value
                    : $plan->tax_treatment?->value,
                'setup_fee_minor' => array_key_exists('setup_fee_minor', $data)
                    ? ($data['setup_fee_minor'] === null || $data['setup_fee_minor'] === '' ? null : (int) $data['setup_fee_minor'])
                    : $plan->setup_fee_minor,
                'provider_price_id' => $data['provider_price_id'] ?? $plan->provider_price_id,
                'is_purchasable' => $isPurchasable,
                'pricing_effective_from' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(AuditAction::PlanPricingUpdated, $plan, null, [
                'code' => $plan->code,
                'previous' => $previous,
                'current' => [
                    'currency' => $plan->currency,
                    'amount_minor' => $plan->amount_minor,
                    'is_purchasable' => $plan->is_purchasable,
                ],
            ], $actor);

            return $plan->fresh();
        });
    }
}
