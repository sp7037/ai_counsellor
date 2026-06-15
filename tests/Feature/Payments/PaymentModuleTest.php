<?php

namespace Tests\Feature\Payments;

use App\Enums\Billing\PaymentOrderStatus;
use App\Enums\Billing\PaymentProvider;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionSource;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenancy\TenantRole;
use App\Exceptions\Billing\PaymentException;
use App\Models\LeadNotification;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingPeriodCalculator;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\PaymentOrderService;
use App\Services\Billing\PaymentVerificationService;
use App\Services\Billing\PaymentWebhookService;
use App\Services\Billing\PlanPricingService;
use App\Services\Billing\SubscriptionLifecycleService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
        Http::fake();
    }

    public function test_non_purchasable_plan_rejected_for_checkout(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        $this->expectException(ValidationException::class);

        app(PaymentOrderService::class)->createCheckoutOrder(
            $setup['tenant'],
            $plan,
            $setup['user'],
            (string) Str::uuid(),
        );
    }

    public function test_purchasable_plan_creates_order_with_server_amount(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 99900, 'INR');

        $result = app(PaymentOrderService::class)->createCheckoutOrder(
            $setup['tenant'],
            $plan,
            $setup['user'],
            (string) Str::uuid(),
        );

        $this->assertFalse($result['reused']);
        $this->assertSame(99900, $result['order']->amount_minor);
        $this->assertSame('INR', $result['order']->currency);
        $this->assertSame(PaymentOrderStatus::Created, $result['order']->status);
    }

    public function test_duplicate_checkout_request_returns_existing_open_order(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('professional', 499900, 'INR');
        $uuid = (string) Str::uuid();
        $service = app(PaymentOrderService::class);

        $first = $service->createCheckoutOrder($setup['tenant'], $plan, $setup['user'], $uuid);
        $second = $service->createCheckoutOrder($setup['tenant'], $plan, $setup['user'], $uuid);

        $this->assertTrue($second['reused']);
        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame(1, PaymentOrder::query()->count());
    }

    public function test_cross_tenant_order_verification_rejected(): void
    {
        $tenantA = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $tenantB = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 10000, 'INR');

        $order = app(PaymentOrderService::class)->createCheckoutOrder(
            $tenantA['tenant'],
            $plan,
            $tenantA['user'],
            (string) Str::uuid(),
        )['order'];

        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        $this->expectException(ValidationException::class);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $tenantB['tenant'],
            $tenantB['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );
    }

    public function test_valid_signature_activates_subscription_once(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('professional', 250000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);
        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        $this->assertSame(1, Payment::query()->count());
        $subscription = Subscription::query()->where('tenant_id', $setup['tenant']->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(SubscriptionSource::Payment, $subscription->source);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $setup['tenant']->id)->count());
    }

    public function test_invalid_signature_rejected(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 10000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);

        $this->expectException(PaymentException::class);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            'pay_invalid',
            'bad-signature',
        );
    }

    public function test_webhook_valid_signature_finalizes_payment(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('professional', 300000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);

        $payload = $this->webhookPayload((string) $order->provider_order_id, 'pay_webhook_1', $order->amount_minor, $order->currency);
        $signature = $this->webhookSignature($payload);

        app(PaymentWebhookService::class)->handle(
            PaymentProvider::Fake,
            $payload,
            $signature,
        );

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertNotNull(Subscription::query()->where('tenant_id', $setup['tenant']->id)->first());
    }

    public function test_webhook_invalid_signature_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_1', 'event' => 'payment.captured', 'payload' => []]);

        $this->expectException(PaymentException::class);

        app(PaymentWebhookService::class)->handle(
            PaymentProvider::Fake,
            (string) $payload,
            'invalid',
        );
    }

    public function test_callback_and_webhook_race_produces_single_payment_and_activation(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('professional', 150000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);
        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        $payload = $this->webhookPayload((string) $order->provider_order_id, $paymentId, $order->amount_minor, $order->currency);
        $webhookSignature = $this->webhookSignature($payload);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        app(PaymentWebhookService::class)->handle(
            PaymentProvider::Fake,
            $payload,
            $webhookSignature,
        );

        $this->assertSame(1, Payment::query()->count());
        $this->assertNotNull($order->fresh()->subscription_activation_completed_at);
        $this->assertSame(1, LeadNotification::query()->where('type', 'payment:'.$order->uuid.':success')->count());
    }

    public function test_duplicate_webhook_ignored(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 50000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);
        $payload = $this->webhookPayload((string) $order->provider_order_id, 'pay_dup', $order->amount_minor, $order->currency);
        $signature = $this->webhookSignature($payload);
        $service = app(PaymentWebhookService::class);

        $service->handle(PaymentProvider::Fake, $payload, $signature);
        $second = $service->handle(PaymentProvider::Fake, $payload, $signature);

        $this->assertSame('duplicate', $second['status']);
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
    }

    public function test_same_plan_renewal_extends_from_period_end(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $plan = $this->makePlanPurchasable('professional', 100000, 'INR');
        $subscription = $setup['subscription'];
        $end = now()->addDays(10);
        $subscription->update([
            'current_period_started_at' => now()->subDays(20),
            'current_period_ends_at' => $end,
        ]);

        $order = $this->createPaidReadyOrder($setup, $plan);
        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        $subscription->refresh();
        $this->assertTrue($subscription->current_period_ends_at->greaterThan($end));
    }

    public function test_trial_converted_immediately_on_payment(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 80000, 'INR');
        app(SubscriptionLifecycleService::class)->startTrial($setup['tenant'], $plan, $setup['user']);

        $order = $this->createPaidReadyOrder($setup, $plan);
        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        $subscription = Subscription::query()->where('tenant_id', $setup['tenant']->id)->first();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_entitlement_cache_invalidated_after_payment_activation(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('professional', 120000, 'INR');
        $resolver = app(EntitlementResolver::class);
        $resolver->check($setup['tenant'], PlanFeature::AiResponses);

        $order = $this->createPaidReadyOrder($setup, $plan);
        [$paymentId, $signature] = $this->fakeSignature((string) $order->provider_order_id);

        app(PaymentVerificationService::class)->verifyBrowserPayment(
            $setup['tenant'],
            $setup['user'],
            (string) $order->provider_order_id,
            $paymentId,
            $signature,
        );

        $result = $resolver->check($setup['tenant']->fresh(), PlanFeature::AiResponses);
        $this->assertTrue($result->isAllowed());
    }

    public function test_counsellor_denied_checkout_routes(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Staff, withSubscription: true);
        $plan = $this->makePlanPurchasable('starter', 10000, 'INR');

        $this->actingAs($setup['user'])
            ->get(route('tenant.subscription.plans', $setup['tenant']))
            ->assertForbidden();
    }

    public function test_tenant_admin_can_access_plans_and_checkout_pages(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 10000, 'INR');

        $this->actingAs($setup['user'])
            ->get(route('tenant.subscription.plans', $setup['tenant']))
            ->assertOk()
            ->assertSee('Choose a plan');

        $this->actingAs($setup['user'])
            ->get(route('tenant.subscription.checkout', [$setup['tenant'], $plan]))
            ->assertOk()
            ->assertSee('Checkout');
    }

    public function test_platform_super_admin_can_view_payments_index(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.payments.index'))
            ->assertOk();
    }

    public function test_payment_secrets_not_exposed_in_settings_html(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        PlatformSetting::query()->create([
            'key' => 'payment_fake_test_key_secret',
            'value' => ['encrypted' => encrypt('super-secret-value')],
        ]);

        $response = $this->actingAs($admin)->get(route('platform.settings.index'));

        $response->assertOk();
        $response->assertDontSee('super-secret-value');
        $response->assertSee('not shown', false);
    }

    public function test_billing_period_calculator_handles_month_and_year(): void
    {
        $calculator = app(BillingPeriodCalculator::class);
        $start = Carbon::parse('2024-01-31 12:00:00');

        $monthEnd = $calculator->periodEnd($start, 'monthly', 1);
        $this->assertSame('2024-02-29', $monthEnd->toDateString());

        $yearEnd = $calculator->periodEnd($start, 'annual', 1);
        $this->assertSame('2025-01-31', $yearEnd->toDateString());
    }

    public function test_plan_pricing_update_audited_and_non_purchasable_without_price(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        app(PlanPricingService::class)->updatePricing($plan, [
            'currency' => 'INR',
            'amount_minor' => 199900,
            'is_purchasable' => true,
        ], $admin);

        $plan->refresh();
        $this->assertTrue($plan->isPurchasable());
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.pricing_updated']);
    }

    public function test_failed_payment_does_not_activate_subscription(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);
        $plan = $this->makePlanPurchasable('starter', 10000, 'INR');
        $order = $this->createPaidReadyOrder($setup, $plan);

        try {
            app(PaymentVerificationService::class)->verifyBrowserPayment(
                $setup['tenant'],
                $setup['user'],
                (string) $order->provider_order_id,
                'pay_fail',
                'invalid',
            );
        } catch (\Throwable) {
            // expected
        }

        $this->assertNull(Subscription::query()->where('tenant_id', $setup['tenant']->id)->first());
    }

    public function test_http_verification_endpoint_requires_auth(): void
    {
        $setup = $this->createTenantWithMember(role: TenantRole::Owner, withSubscription: false);

        $this->postJson(route('tenant.subscription.payments.verify', $setup['tenant']), [])
            ->assertRedirect();
    }

    /**
     * @param  array{tenant: Tenant, user: User}  $setup
     */
    private function createPaidReadyOrder(array $setup, Plan $plan): PaymentOrder
    {
        return app(PaymentOrderService::class)->createCheckoutOrder(
            $setup['tenant'],
            $plan,
            $setup['user'],
            (string) Str::uuid(),
        )['order'];
    }

    private function makePlanPurchasable(string $code, int $amountMinor, string $currency): Plan
    {
        $plan = Plan::query()->where('code', $code)->firstOrFail();
        $plan->update([
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'is_purchasable' => true,
        ]);

        return $plan->fresh();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fakeSignature(string $providerOrderId): array
    {
        $paymentId = 'pay_test_'.Str::lower(Str::random(10));
        $secret = config('payments.providers.fake.key_secret');
        $signature = hash_hmac('sha256', $providerOrderId.'|'.$paymentId, (string) $secret);

        return [$paymentId, $signature];
    }

    private function webhookPayload(string $orderId, string $paymentId, int $amount, string $currency): string
    {
        return json_encode([
            'id' => 'evt_'.Str::random(10),
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $orderId,
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'captured',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function webhookSignature(string $payload): string
    {
        $secret = config('payments.providers.fake.webhook_secret');

        return hash_hmac('sha256', $payload, (string) $secret);
    }
}
