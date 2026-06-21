<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionEventType;
use App\Enums\Billing\SubscriptionSource;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\SubscriptionLifecycleService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlatformTenantSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
    }

    public function test_platform_super_admin_can_assign_trial_to_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();
        $trialPlan = Plan::query()->where('code', 'trial')->firstOrFail();

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant])
            ->set('plan_id', $trialPlan->id)
            ->set('reason', 'Staging trial for onboarding')
            ->call('assignTrial')
            ->assertHasNoErrors();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(SubscriptionSource::Trial, $subscription->source);
        $this->assertDatabaseHas('subscription_events', [
            'tenant_id' => $tenant->id,
            'event_type' => SubscriptionEventType::TrialStarted->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => AuditAction::SubscriptionTrialStarted->value,
        ]);
    }

    public function test_platform_super_admin_can_assign_paid_plan_manually(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant])
            ->set('plan_id', $plan->id)
            ->set('reason', 'Manual enterprise onboarding')
            ->call('assignPlan')
            ->assertHasNoErrors();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(SubscriptionSource::Manual, $subscription->source);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => AuditAction::SubscriptionAssigned->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_tenant_entitlements_unlock_after_manual_assignment(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(withSubscription: false);
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant])
            ->set('plan_id', $plan->id)
            ->set('reason', 'Unlock features for QA')
            ->call('assignPlan')
            ->assertHasNoErrors();

        app(EntitlementResolver::class)->clearCache();

        $result = app(EntitlementResolver::class)->check($tenant->fresh(), PlanFeature::HumanHandoff);

        $this->assertTrue($result->isAllowed());

        $this->actingAs($owner)
            ->get(route('tenant.subscription', $tenant))
            ->assertOk()
            ->assertDontSee('No active subscription', false);
    }

    public function test_duplicate_trial_assignment_is_blocked(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();
        $trialPlan = Plan::query()->where('code', 'trial')->firstOrFail();
        $lifecycle = app(SubscriptionLifecycleService::class);
        $lifecycle->startTrial($tenant, $trialPlan, $admin, reason: 'Initial trial');

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant->fresh()])
            ->set('plan_id', $trialPlan->id)
            ->set('reason', 'Duplicate attempt')
            ->call('assignTrial')
            ->assertHasErrors('subscription');

        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_change_plan_replaces_previous_plan_safely(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();
        $starter = Plan::query()->where('code', 'starter')->firstOrFail();
        $professional = Plan::query()->where('code', 'professional')->firstOrFail();

        app(SubscriptionLifecycleService::class)->assignPlan($tenant, $starter, $admin, 'Initial plan');

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant->fresh()])
            ->set('plan_id', $professional->id)
            ->set('reason', 'Upgrade for counsellor module')
            ->call('changePlan')
            ->assertHasNoErrors();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame($professional->id, $subscription->plan_id);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => SubscriptionEventType::PlanChanged->value,
        ]);
    }

    public function test_cancel_subscription_restricts_tenant_entitlements(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(withSubscription: true);
        $subscription = $tenant->subscription()->firstOrFail();

        $this->actingAs($admin);

        Volt::test('platform.tenants.subscription', ['tenant' => $tenant])
            ->set('reason', 'End of pilot programme')
            ->call('cancelSubscription')
            ->assertHasNoErrors();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);

        app(EntitlementResolver::class)->clearCache();

        $result = app(EntitlementResolver::class)->check($tenant->fresh(), PlanFeature::AiResponses);
        $this->assertSame(EntitlementOutcome::SubscriptionExpired, $result->outcome);

        $this->actingAs($owner)
            ->get(route('tenant.leads.index', $tenant))
            ->assertRedirect(route('tenant.subscription', $tenant));
    }

    public function test_tenant_detail_page_links_to_subscription_management(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->assertSee('Assign subscription')
            ->assertSeeHtml(route('platform.tenants.subscription', $tenant));
    }

    public function test_subscription_notice_component_has_readable_warning_styles(): void
    {
        $html = Blade::render('<x-subscription-notice>Readable warning copy</x-subscription-notice>');

        $this->assertStringContainsString('text-amber-100', $html);
        $this->assertStringContainsString('Readable warning copy', $html);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
    }

    public function test_route_cache_succeeds_without_duplicate_verification_route(): void
    {
        $verificationRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->getName() === 'verification.verify');

        $this->assertCount(1, $verificationRoutes);

        Artisan::call('route:cache');
        $this->assertFileExists(base_path('bootstrap/cache/routes-v7.php'));

        Artisan::call('route:clear');
    }
}
