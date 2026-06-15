<?php

namespace Database\Factories;

use App\Enums\Billing\SubscriptionSource;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active->value,
            'source' => SubscriptionSource::Manual->value,
            'current_period_started_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
            'cancel_at_period_end' => false,
        ];
    }

    public function trialing(int $days = 14): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing->value,
            'source' => SubscriptionSource::Trial->value,
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays($days),
            'current_period_started_at' => null,
            'current_period_ends_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired->value,
            'expired_at' => now(),
            'current_period_ends_at' => now()->subDay(),
        ]);
    }

    public function grace(int $days = 7): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Grace->value,
            'grace_ends_at' => now()->addDays($days),
        ]);
    }
}
