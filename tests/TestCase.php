<?php

namespace Tests;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Enums\Widget\TenantDomainStatus;
use App\Enums\Widget\WidgetKeyStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAiConfig;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WidgetKey;
use App\Services\AI\TenantAiConfigService;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();

        parent::tearDown();
    }

    protected function createTenantWithMember(
        array $tenantAttributes = [],
        ?User $user = null,
        TenantRole $role = TenantRole::Staff,
        MembershipStatus $membershipStatus = MembershipStatus::Active,
        bool $withSubscription = true,
    ): array {
        $tenant = Tenant::factory()->create(array_merge([
            'status' => TenantStatus::Active->value,
            'activated_at' => now(),
        ], $tenantAttributes));

        $user ??= User::factory()->create();

        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $membershipStatus->value,
            'is_owner' => $role === TenantRole::Owner,
        ]);

        if (app()->environment('testing') && $withSubscription) {
            $this->seedPlans();
            $plan = Plan::query()->where('code', 'professional')->first();

            if ($plan !== null && ! $tenant->subscription()->exists()) {
                try {
                    app(SubscriptionLifecycleService::class)->assignPlan(
                        $tenant,
                        $plan,
                        $user,
                        'Automated test subscription',
                    );
                } catch (\Throwable) {
                    // Subscription assignment is best-effort for legacy tests.
                }
            }
        }

        return compact('tenant', 'user', 'membership');
    }

    protected function withTenantContext(User $user, Tenant $tenant): TenantContext
    {
        $context = app(TenantContext::class);
        $context->clear();
        $context->resolveForUser($user, $tenant);
        $context->enforceIsolation();

        return $context;
    }

    protected function createWidgetReadyTenant(
        array $tenantAttributes = [],
        ?User $user = null,
        TenantRole $role = TenantRole::Owner,
    ): array {
        $result = $this->createTenantWithMember($tenantAttributes, $user, $role);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($result['user'], $result['tenant']);
        app(TenantContext::class)->enforceIsolation();

        $key = WidgetKey::query()->create([
            'tenant_id' => $result['tenant']->id,
            'public_key' => 'wk_test_'.str()->random(24),
            'name' => 'Test key',
            'status' => WidgetKeyStatus::Active->value,
            'created_by' => $result['user']->id,
        ]);

        $domain = TenantDomain::query()->create([
            'tenant_id' => $result['tenant']->id,
            'domain' => '127.0.0.1',
            'status' => TenantDomainStatus::Verified->value,
            'verified_at' => now(),
            'created_by' => $result['user']->id,
        ]);

        app(TenantContext::class)->clear();

        return array_merge($result, compact('key', 'domain'));
    }

    protected function widgetSessionToken(WidgetKey $key): string
    {
        return (string) $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');
    }

    protected function configureTenantAi(
        Tenant $tenant,
        User $user,
        array $overrides = [],
    ): TenantAiConfig {
        $this->withTenantContext($user, $tenant);

        return app(TenantAiConfigService::class)->upsert($tenant, array_merge([
            'provider' => 'fake',
            'model' => 'fake-model',
            'temperature' => 0.2,
            'max_output_tokens' => 400,
            'timeout_seconds' => 15,
            'enabled' => true,
            'credential_mode' => 'platform_managed',
        ], $overrides), $user);
    }

    protected function seedPlans(): void
    {
        $this->seed(PlansSeeder::class);
    }

    /**
     * @return array{tenant: Tenant, user: User, membership: TenantMembership, subscription: Subscription, plan: Plan}
     */
    protected function createTenantWithSubscription(
        ?string $planCode = 'professional',
        array $tenantAttributes = [],
        ?User $user = null,
        TenantRole $role = TenantRole::Owner,
    ): array {
        $result = $this->createTenantWithMember($tenantAttributes, $user, $role);

        if ($planCode !== 'professional') {
            $plan = Plan::query()->where('code', $planCode)->firstOrFail();
            app(SubscriptionLifecycleService::class)->changePlan(
                $result['tenant']->subscription()->first(),
                $plan,
                $result['user'],
                'Test plan assignment',
            );
        }

        $subscription = $result['tenant']->subscription()->with('plan.entitlements')->first();
        $plan = $subscription->plan;

        app(TenantContext::class)->clear();

        return array_merge($result, compact('subscription', 'plan'));
    }
}
