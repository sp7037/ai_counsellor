<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreatePlatformSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_super_admin_using_env_password(): void
    {
        putenv('PLATFORM_BOOTSTRAP_PASSWORD=SecureLocalPass123');

        try {
            $exitCode = Artisan::call('platform:create-super-admin', [
                '--name' => 'Bootstrap Admin',
                '--email' => 'bootstrap@example.test',
            ]);
        } finally {
            putenv('PLATFORM_BOOTSTRAP_PASSWORD');
        }

        $this->assertSame(0, $exitCode);

        $user = User::query()->where('email', 'bootstrap@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(PlatformRole::SuperAdmin, $user->platform_role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertStringNotContainsString('SecureLocalPass123', Artisan::output());
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $exitCode = Artisan::call('platform:create-super-admin', [
            '--name' => 'Blocked',
            '--email' => 'blocked@example.test',
        ]);

        $this->assertSame(1, $exitCode);
    }
}
