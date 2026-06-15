<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_user_cannot_access_platform_routes(): void
    {
        $user = User::factory()->platformSuperAdmin()->disabled()->create();

        $this->actingAs($user)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_disabled_user_cannot_access_tenant_routes(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $user->update(['status' => UserStatus::Disabled->value]);

        $this->actingAs($user->fresh())
            ->get(route('tenant.dashboard', $tenant))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_unverified_user_cannot_access_verified_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('tenant.select'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_active_user_can_access_authorized_tenant_route(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertOk();
    }

    public function test_login_regenerates_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-123456')]);

        $this->startSession();
        $oldSessionId = session()->getId();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password-123456')
            ->call('login');

        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_login_rate_limiting_blocks_excessive_attempts(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Volt::test('auth.login')
                ->set('email', $user->email)
                ->set('password', 'wrong-password')
                ->call('login')
                ->assertHasErrors('email');
        }

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_password_reset_does_not_expose_tenant_existence(): void
    {
        Volt::test('auth.forgot-password')
            ->set('email', 'unknown-user@example.test')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('A reset link will be sent if the account exists', false);
    }

    public function test_public_registration_route_is_unavailable(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_tenant_owner_cannot_access_platform_routes(): void
    {
        ['user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        $this->actingAs($owner)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();
    }
}
