<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_protected_platform_routes(): void
    {
        $this->get(route('platform.tenants.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_protected_tenant_routes(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->get(route('tenant.dashboard', $tenant))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('tenant.select'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_authenticated_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_disabled_users_cannot_access_protected_areas(): void
    {
        $user = User::factory()->disabled()->create();

        $response = $this->actingAs($user)->get(route('tenant.select'));

        $response->assertForbidden();
        $this->assertGuest();
    }
}
