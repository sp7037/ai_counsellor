<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantLayoutConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tenantAdminPagesProvider(): array
    {
        return [
            'dashboard' => ['tenant.dashboard'],
            'subscription' => ['tenant.subscription'],
            'leads' => ['tenant.leads.index'],
            'counsellors' => ['tenant.counsellors.index'],
            'conversations' => ['tenant.conversations.index'],
            'knowledge' => ['tenant.knowledge.index'],
            'configuration' => ['tenant.configuration.index'],
            'ai configuration' => ['tenant.ai.configuration'],
            'members' => ['tenant.members.index'],
            'widget' => ['tenant.widget.index'],
            'integrations' => ['tenant.integrations.index'],
        ];
    }

    #[DataProvider('tenantAdminPagesProvider')]
    public function test_tenant_admin_pages_use_dark_tenant_shell(string $routeName): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $response = $this->actingAs($user)->get(route($routeName, $tenant));

        $response->assertOk();
        $response->assertSee('tenant-admin-shell', false);
        $response->assertSee('Tenant workspace', false);
        $response->assertSee('Configuration', false);
        $response->assertSee('AI Counsellor', false);
        $response->assertDontSee('class="min-h-screen bg-white antialiased dark:bg-neutral-950"', false);
    }

    public function test_tenant_shell_pins_stable_dark_context_for_readability(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $response = $this->actingAs($user)->get(route('tenant.configuration.index', $tenant));

        $response->assertOk();
        // The dark context is pinned on the body so labels stay legible even when
        // the global appearance toggle removes `.dark` from <html>.
        $response->assertSee('class="dark min-h-screen bg-neutral-950', false);
        $response->assertSee('tenant-admin-shell', false);
    }

    public function test_tenant_forms_render_their_labels(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($user)
            ->get(route('tenant.counsellors.create', $tenant))
            ->assertOk()
            ->assertSee('Full name', false)
            ->assertSee('Temporary password', false)
            ->assertSee('Designation', false);

        $this->actingAs($user)
            ->get(route('tenant.configuration.locations', $tenant))
            ->assertOk()
            ->assertSee('Location name', false)
            ->assertSee('PIN code', false);
    }

    public function test_subscription_page_uses_readable_dark_panels(): void
    {
        $html = Blade::render('<x-tenant.panel heading="Usage"><p class="text-zinc-200">Leads</p></x-tenant.panel>');

        $this->assertStringContainsString('bg-zinc-900', $html);
        $this->assertStringContainsString('text-zinc-100', $html);
        $this->assertStringContainsString('Leads', $html);
    }

    public function test_branding_logo_path_resolves_existing_asset(): void
    {
        $path = Branding::logoPath();

        $this->assertFileExists(public_path($path));
        $this->assertStringContainsString('images/Ai_counsellor_logo', $path);
        $this->assertFileExists(public_path(Branding::faviconPath()));
    }

    public function test_platform_overview_still_renders_with_logo(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.overview'))
            ->assertOk()
            ->assertSee('AI Counsellor', false)
            ->assertSee('Platform Super Admin', false);
    }
}
