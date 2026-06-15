<?php

namespace App\Services\Billing;

use Illuminate\Support\Carbon;

class BillingPeriodCalculator
{
    public function periodEnd(Carbon $start, string $billingInterval, int $intervalCount = 1): Carbon
    {
        $count = max(1, $intervalCount);

        return match ($billingInterval) {
            'annual', 'yearly' => $start->copy()->addYearsNoOverflow($count),
            'monthly' => $start->copy()->addMonthsNoOverflow($count),
            default => $start->copy()->addMonthsNoOverflow($count),
        };
    }

    public function renewalStart(Carbon $now, ?Carbon $currentPeriodEnd, bool $stillActive): Carbon
    {
        if ($stillActive && $currentPeriodEnd !== null && $currentPeriodEnd->greaterThan($now)) {
            return $currentPeriodEnd->copy();
        }

        return $now->copy();
    }
}
