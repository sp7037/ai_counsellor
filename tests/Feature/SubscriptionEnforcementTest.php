<?php

namespace Tests\Feature;

use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\AiRun;
use App\Models\Plan;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Tenancy\TenantLifecycleService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
        Http::fake();
    }

    public function test_entitlement_resolver_allows_active_subscription_feature(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $resolver = app(EntitlementResolver::class);

        $result = $resolver->check($setup['tenant'], PlanFeature::HumanHandoff);

        $this->assertTrue($result->isAllowed());
    }

    public function test_missing_feature_denied(): void
    {
        $setup = $this->createTenantWithSubscription('starter');
        $resolver = app(EntitlementResolver::class);

        $result = $resolver->check($setup['tenant'], PlanFeature::HumanHandoff);

        $this->assertSame(EntitlementOutcome::FeatureNotIncluded, $result->outcome);
    }

    public function test_expired_subscription_denies_operational_feature(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $lifecycle = app(SubscriptionLifecycleService::class);
        $lifecycle->expire($setup['subscription']->fresh(), $setup['user'], 'test');

        $result = app(EntitlementResolver::class)->check($setup['tenant']->fresh(), PlanFeature::AiResponses);

        $this->assertSame(EntitlementOutcome::SubscriptionExpired, $result->outcome);
    }

    public function test_suspended_tenant_overrides_commercial_access(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $setup['tenant']->update(['status' => TenantStatus::Suspended->value, 'suspended_at' => now()]);

        app(EntitlementResolver::class)->clearCache();

        $result = app(EntitlementResolver::class)->check($setup['tenant']->fresh(), PlanFeature::Widget);

        $this->assertSame(EntitlementOutcome::TenantSuspended, $result->outcome);
    }

    public function test_tenant_admin_can_access_subscription_page_when_expired(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        app(SubscriptionLifecycleService::class)->expire($setup['subscription']->fresh(), $setup['user'], 'test');

        $this->actingAs($setup['user'])
            ->get(route('tenant.subscription', $setup['tenant']))
            ->assertOk();
    }

    public function test_expired_subscription_redirects_operational_route_to_subscription_page(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        app(SubscriptionLifecycleService::class)->expire($setup['subscription']->fresh(), $setup['user'], 'test');

        $this->actingAs($setup['user'])
            ->get(route('tenant.leads.index', $setup['tenant']))
            ->assertRedirect(route('tenant.subscription', $setup['tenant']));
    }

    public function test_counsellor_workspace_denied_when_subscription_expired(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $staffUser = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $setup['tenant']->id,
            'user_id' => $staffUser->id,
            'role' => TenantRole::Staff->value,
            'status' => MembershipStatus::Active->value,
        ]);

        app(SubscriptionLifecycleService::class)->expire($setup['subscription']->fresh(), $setup['user'], 'test');

        $this->actingAs($staffUser)
            ->get(route('workspace.dashboard', $setup['tenant']))
            ->assertForbidden();
    }

    public function test_ai_not_called_when_feature_unavailable(): void
    {
        $setup = $this->createWidgetReadyTenant();
        $this->configureTenantAi($setup['tenant'], $setup['user']);

        app(SubscriptionLifecycleService::class)->expire(
            $setup['tenant']->subscription()->first(),
            $setup['user'],
            'test',
        );

        $token = $this->widgetSessionToken($setup['key']);

        $beforeRuns = AiRun::query()->count();

        $this->postJson('/widget/v1/messages', [
            'body' => 'Hello',
            'request_id' => (string) str()->uuid(),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $this->assertSame($beforeRuns, AiRun::query()->count());
    }

    public function test_widget_session_blocked_when_tenant_suspended(): void
    {
        $setup = $this->createWidgetReadyTenant();
        $setup['tenant']->update([
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
        ]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $setup['key']->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_handoff_denied_when_feature_not_in_plan(): void
    {
        $setup = $this->createWidgetReadyTenant();
        $starter = Plan::query()->where('code', 'starter')->firstOrFail();
        app(SubscriptionLifecycleService::class)->changePlan(
            $setup['tenant']->subscription()->first(),
            $starter,
            $setup['user'],
            'downgrade test',
        );

        $token = $this->widgetSessionToken($setup['key']);

        $this->postJson('/widget/v1/handoff', [
            'handoff_request_uuid' => (string) str()->uuid(),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_subscription_lifecycle_records_events_and_audit(): void
    {
        $setup = $this->createTenantWithMember(withSubscription: false);
        $plan = Plan::query()->where('code', 'trial')->firstOrFail();
        $lifecycle = app(SubscriptionLifecycleService::class);

        $subscription = $lifecycle->startTrial($setup['tenant'], $plan, $setup['user'], 7, 'trial test');
        $lifecycle->activate($subscription, $setup['user'], reason: 'converted');

        $this->assertDatabaseHas('subscription_events', [
            'tenant_id' => $setup['tenant']->id,
            'event_type' => 'trial_started',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscription.activated',
            'tenant_id' => $setup['tenant']->id,
        ]);
    }

    public function test_platform_super_admin_can_view_plans(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('platform.plans.index'))
            ->assertOk();
    }

    public function test_tenant_user_cannot_access_platform_plans(): void
    {
        $setup = $this->createTenantWithSubscription();

        $this->actingAs($setup['user'])
            ->get(route('platform.plans.index'))
            ->assertForbidden();
    }

    public function test_entitlement_cache_cleared_after_suspension(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $resolver = app(EntitlementResolver::class);

        $this->assertTrue($resolver->check($setup['tenant'], PlanFeature::Widget)->isAllowed());

        $platformAdmin = User::factory()->create(['platform_role' => 'super_admin']);
        $suspended = app(TenantLifecycleService::class)->suspend(
            $setup['tenant'],
            'test suspension',
            $platformAdmin,
        );

        $this->assertFalse($suspended->allowsTenantAccess());

        $resolver->clearCache();
        $this->assertSame(
            EntitlementOutcome::TenantSuspended,
            $resolver->check($suspended, PlanFeature::Widget)->outcome,
        );
    }

    public function test_maintain_command_expires_trial_idempotently(): void
    {
        $setup = $this->createTenantWithMember(withSubscription: false);
        $plan = Plan::query()->where('code', 'trial')->firstOrFail();
        $subscription = app(SubscriptionLifecycleService::class)->startTrial($setup['tenant'], $plan, $setup['user'], 1, 'short trial');
        $subscription->update(['trial_ends_at' => now()->subMinute()]);

        $this->artisan('subscriptions:maintain')->assertSuccessful();
        $this->artisan('subscriptions:maintain')->assertSuccessful();

        $this->assertSame(
            SubscriptionStatus::Expired,
            $subscription->fresh()->status,
        );
    }

    public function test_cross_tenant_subscription_data_not_exposed_on_tenant_page(): void
    {
        $a = $this->createTenantWithSubscription('starter');
        $b = $this->createTenantWithSubscription('enterprise');

        $response = $this->actingAs($a['user'])
            ->get(route('tenant.subscription', $a['tenant']));

        $response->assertOk();
        $response->assertDontSee($b['tenant']->name);
        $response->assertDontSee('Enterprise');
    }
}
