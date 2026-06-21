<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Models\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TenantConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_staff_cannot_create_service(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Staff);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.services', ['tenant' => $tenant])
            ->set('name', 'Admissions counselling')
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseCount('services', 0);
    }

    public function test_tenant_admin_can_create_service_with_audit(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.services', ['tenant' => $tenant])
            ->set('name', 'Career guidance')
            ->set('description', 'One-to-one guidance sessions')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('services', ['name' => 'Career guidance']);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ServiceCreated->value]);
    }

    public function test_branding_update_is_audited(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.branding', ['tenant' => $tenant])
            ->set('displayName', 'SR World Counselling')
            ->set('assistantName', 'Admissions Assistant')
            ->set('primaryColor', '#112233')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::BrandingUpdated->value]);
    }

    public function test_logo_upload_rejects_invalid_mime(): void
    {
        Storage::fake('public');
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $file = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');

        Volt::test('tenant.configuration.branding', ['tenant' => $tenant])
            ->set('logoUpload', $file)
            ->call('uploadLogo')
            ->assertHasErrors(['logoUpload']);
    }

    public function test_logo_upload_persists_logo_path_for_tenant_settings(): void
    {
        Storage::fake('public');
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $file = UploadedFile::fake()->image('tenant-logo.png', 180, 180);

        Volt::test('tenant.configuration.branding', ['tenant' => $tenant])
            ->set('logoUpload', $file)
            ->call('uploadLogo')
            ->assertHasNoErrors();

        $logoPath = (string) TenantSettings::query()->where('tenant_id', $tenant->id)->value('logo_path');
        $this->assertNotSame('', $logoPath);
        $this->assertStringContainsString('tenant-logos/'.$tenant->uuid.'/', $logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_office_hours_update_is_audited(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.office-hours', ['tenant' => $tenant])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::OfficeHoursUpdated->value]);
    }
}
