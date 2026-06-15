<?php

namespace Database\Factories;

use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $code = 'plan_'.Str::lower(Str::random(8));

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'description' => 'Test plan',
            'billing_interval' => 'monthly',
            'display_order' => 0,
            'is_public' => true,
            'status' => PlanStatus::Active->value,
        ];
    }
}
