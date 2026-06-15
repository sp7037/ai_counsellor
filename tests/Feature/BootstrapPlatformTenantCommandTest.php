<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapPlatformTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_bootstraps_tenant_owner_using_env_password(): void
    {
        User::factory()->platformSuperAdmin()->create();

        Plan::factory()->create(['code' => 'trial', 'name' => 'Trial']);

        putenv('TENANT_BOOTSTRAP_PASSWORD=SecureLocalPass123');

        try {
            $exitCode = Artisan::call('platform:bootstrap-tenant', [
                '--tenant-name' => 'Acme Counselling',
                '--tenant-slug' => 'acme-counselling',
                '--name' => 'Tenant Owner',
                '--email' => 'owner@example.test',
                '--plan' => 'trial',
            ]);
        } finally {
            putenv('TENANT_BOOTSTRAP_PASSWORD');
        }

        $this->assertSame(0, $exitCode);

        $tenant = Tenant::query()->where('slug', 'acme-counselling')->first();
        $owner = User::query()->where('email', 'owner@example.test')->first();

        $this->assertNotNull($tenant);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertNotNull($owner);
        $this->assertSame(TenantRole::Owner, $owner->tenantRoleFor($tenant));
        $this->assertNotNull($tenant->subscription);
        $this->assertStringNotContainsString('SecureLocalPass123', Artisan::output());
    }

    public function test_command_resets_password_for_existing_tenant_owner(): void
    {
        User::factory()->platformSuperAdmin()->create();
        $result = $this->createTenantWithMember([], role: TenantRole::Owner);
        $tenant = $result['tenant'];
        $owner = $result['user'];

        putenv('TENANT_BOOTSTRAP_PASSWORD=AnotherSecurePass1');

        try {
            $exitCode = Artisan::call('platform:bootstrap-tenant', [
                '--tenant-slug' => $tenant->slug,
                '--name' => $owner->name,
                '--email' => $owner->email,
                '--no-activate' => true,
            ]);
        } finally {
            putenv('TENANT_BOOTSTRAP_PASSWORD');
        }

        $this->assertSame(0, $exitCode);
        $this->assertTrue(
            Hash::check('AnotherSecurePass1', $owner->fresh()->password),
        );
    }

    public function test_command_is_blocked_in_production(): void
    {
        User::factory()->platformSuperAdmin()->create();
        $this->app->detectEnvironment(fn () => 'production');

        $exitCode = Artisan::call('platform:bootstrap-tenant', [
            '--tenant-name' => 'Blocked',
            '--tenant-slug' => 'blocked',
            '--name' => 'Owner',
            '--email' => 'blocked-owner@example.test',
        ]);

        $this->assertSame(1, $exitCode);
    }
}
