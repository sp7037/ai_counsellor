<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\WidgetKey;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WidgetIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_admin_cannot_rotate_tenant_b_widget_key(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        Volt::test('tenant.widget.index', ['tenant' => $tenantA])
            ->call('rotateKey', $keyB->uuid)
            ->assertStatus(404);

        $this->assertTrue($keyB->fresh()->isActive());
    }

    public function test_tenant_a_admin_cannot_verify_tenant_b_domain(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createWidgetReadyTenant();
        ['domain' => $domainB] = $this->createWidgetReadyTenant();

        $domainB->update(['status' => 'pending', 'verified_at' => null]);

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        Volt::test('tenant.widget.index', ['tenant' => $tenantA])
            ->call('verifyDomain', $domainB->id)
            ->assertStatus(404);
    }

    public function test_tenant_a_cannot_view_tenant_b_conversations(): void
    {
        ['tenant' => $tenantA, 'user' => $userA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', ['widget_key' => $keyB->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ]);

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        $visible = Conversation::query()->pluck('tenant_id')->all();
        $this->assertNotContains($tenantB->id, $visible);
    }

    public function test_widget_keys_are_scoped_to_active_tenant_context(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB] = $this->createWidgetReadyTenant();

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        $keys = WidgetKey::query()->pluck('tenant_id')->unique()->all();
        $this->assertSame([$tenantA->id], $keys);
    }

    public function test_cross_tenant_widget_session_count(): void
    {
        $cases = 0;

        ['key' => $keyA] = $this->createWidgetReadyTenant();
        ['key' => $keyB] = $this->createWidgetReadyTenant();

        $tokenA = $this->postJson('/widget/v1/session', ['widget_key' => $keyA->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $tokenB = $this->postJson('/widget/v1/session', ['widget_key' => $keyB->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->postJson('/widget/v1/messages', ['body' => 'Tenant A secret'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk();
        $cases++;

        $this->postJson('/widget/v1/messages', ['body' => 'Tenant B secret'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk();
        $cases++;

        app(TenantContext::class)->clear();

        $this->assertDatabaseCount('conversations', 2);
        $this->assertSame(2, $cases);
    }
}
