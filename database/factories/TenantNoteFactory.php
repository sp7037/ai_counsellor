<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantNote>
 */
class TenantNoteFactory extends Factory
{
    protected $model = TenantNote::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->active(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
