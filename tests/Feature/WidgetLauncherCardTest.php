<?php

namespace Tests\Feature;

use App\Enums\Configuration\LauncherMode;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\TenantWidgetSettings;
use App\Services\Configuration\TenantLauncherConfigurationService;
use App\Services\Configuration\WidgetPublicConfigService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WidgetLauncherCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_launcher_mode_is_circle_for_backward_compatibility(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $config = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertSame(LauncherMode::Circle->value, $config['launcher']['mode']);
        $this->assertArrayHasKey('card', $config['launcher']);
    }

    public function test_tenant_admin_can_save_card_launcher_settings(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.launcher', ['tenant' => $tenant])
            ->set('launcherMode', LauncherMode::Card->value)
            ->set('cardTitle', 'Need help choosing MBBS abroad?')
            ->set('cardSubtitle', 'Ask your free admission counsellor.')
            ->set('cardCtaText', 'Start free counselling')
            ->set('cardTrustText', 'Free guidance • No obligation')
            ->set('cardDelaySeconds', 5)
            ->set('cardDismissHours', 5)
            ->set('cardAnimation', 'soft_slide_up')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_widget_settings', [
            'tenant_id' => $tenant->id,
            'launcher_mode' => LauncherMode::Card->value,
            'launcher_card_title' => 'Need help choosing MBBS abroad?',
            'launcher_card_cta_text' => 'Start free counselling',
        ]);
    }

    public function test_widget_config_returns_tenant_card_launcher_settings(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        $widgetSettings = TenantWidgetSettings::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $widgetSettings->update([
            'launcher_mode' => LauncherMode::Card->value,
            'launcher_card_title' => 'Tenant card title',
            'launcher_card_subtitle' => 'Tenant card subtitle',
            'launcher_card_cta_text' => 'Chat now',
            'launcher_card_trust_text' => 'Trusted guidance',
            'launcher_card_delay_seconds' => 3,
            'launcher_card_dismiss_hours' => 5,
            'launcher_card_animation' => 'soft_bounce_once',
        ]);

        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertSame(LauncherMode::Card->value, $config['launcher']['mode']);
        $this->assertSame('Tenant card title', $config['launcher']['card']['title']);
        $this->assertSame('Tenant card subtitle', $config['launcher']['card']['subtitle']);
        $this->assertSame('Chat now', $config['launcher']['card']['cta_text']);
        $this->assertSame('Trusted guidance', $config['launcher']['card']['trust_text']);
        $this->assertSame(3, $config['launcher']['card']['delay_seconds']);
        $this->assertSame(5, $config['launcher']['card']['dismiss_reshow_seconds']);
        $this->assertSame('soft_bounce_once', $config['launcher']['card']['animation']);
    }

    public function test_platform_defaults_are_used_when_tenant_card_fields_are_missing(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_card_title'], ['value' => 'Platform title']);
        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_card_subtitle'], ['value' => 'Platform subtitle']);
        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_card_cta_text'], ['value' => 'Platform CTA']);

        $config = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertSame('Platform title', $config['launcher']['card']['title']);
        $this->assertSame('Platform subtitle', $config['launcher']['card']['subtitle']);
        $this->assertSame('Platform CTA', $config['launcher']['card']['cta_text']);
    }

    public function test_tenant_card_settings_override_platform_defaults(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_card_title'], ['value' => 'Platform title']);

        TenantWidgetSettings::query()->where('tenant_id', $tenant->id)->update([
            'launcher_card_title' => 'Tenant wins',
        ]);

        $title = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration.launcher.card.title');

        $this->assertSame('Tenant wins', $title);
    }

    public function test_card_image_falls_back_to_tenant_logo_when_no_card_image_uploaded(): void
    {
        Storage::fake('public');

        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        $path = 'tenant-logos/'.$tenant->uuid.'/logo.png';
        Storage::disk('public')->put($path, 'fake-image');

        TenantSettings::query()->where('tenant_id', $tenant->id)->update(['logo_path' => $path]);

        $imageUrl = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration.launcher.card.image_url');

        $this->assertStringContainsString('/storage/', (string) $imageUrl);
        $this->assertStringNotContainsString(storage_path(), (string) $imageUrl);
    }

    public function test_cross_tenant_launcher_settings_are_isolated(): void
    {
        ['tenant' => $tenantA, 'user' => $userA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        $this->withTenantContext($userA, $tenantA);
        TenantWidgetSettings::query()->where('tenant_id', $tenantA->id)->update([
            'launcher_mode' => LauncherMode::Card->value,
            'launcher_card_title' => 'Tenant A card',
        ]);

        $configA = app(WidgetPublicConfigService::class)->chromeFor($tenantA);
        $configB = app(WidgetPublicConfigService::class)->chromeFor($tenantB);

        $this->assertSame('Tenant A card', $configA['launcher']['card']['title']);
        $this->assertNotSame('Tenant A card', $configB['launcher']['card']['title']);
    }

    public function test_disabled_launcher_mode_is_exposed_in_public_config(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        TenantWidgetSettings::query()->where('tenant_id', $tenant->id)->update([
            'launcher_mode' => LauncherMode::Disabled->value,
        ]);

        $mode = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration.launcher.mode');

        $this->assertSame(LauncherMode::Disabled->value, $mode);
    }

    public function test_invalid_card_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.configuration.launcher', ['tenant' => $tenant])
            ->set('cardImageUpload', UploadedFile::fake()->create('bad.txt', 10, 'text/plain'))
            ->call('uploadCardImage')
            ->assertHasErrors(['cardImageUpload']);
    }

    public function test_card_image_upload_persists_for_correct_tenant(): void
    {
        Storage::fake('public');

        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('counsellor.jpg', 512, 512);

        app(TenantLauncherConfigurationService::class)->uploadCardImage($tenant, $file, $user);

        $record = TenantWidgetSettings::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotNull($record->launcher_card_image_path);
        $this->assertStringStartsWith('tenant-launcher-cards/'.$tenant->uuid.'/', $record->launcher_card_image_path);
        Storage::disk('public')->assertExists($record->launcher_card_image_path);
    }

    public function test_built_widget_bundle_includes_card_launcher_markers(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        foreach ([
            'ac-widget-card',
            'ac-widget-card-cta',
            'ac_widget_card_dismiss_',
            'prefers-reduced-motion',
        ] as $marker) {
            $this->assertStringContainsString($marker, $js, "Missing marker in built widget bundle: {$marker}");
        }
    }
}
