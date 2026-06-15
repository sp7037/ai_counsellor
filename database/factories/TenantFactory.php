<?php

namespace Database\Factories;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'legal_name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => TenantStatus::Pending->value,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Active->value,
            'activated_at' => now(),
        ]);
    }

    public function suspended(?string $reason = 'Test suspension'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Cancelled->value,
        ]);
    }
}
