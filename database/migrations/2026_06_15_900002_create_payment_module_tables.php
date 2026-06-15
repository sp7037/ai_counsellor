<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable()->after('billing_interval');
            $table->unsignedBigInteger('amount_minor')->nullable()->after('currency');
            $table->unsignedSmallInteger('billing_interval_count')->default(1)->after('amount_minor');
            $table->string('tax_treatment', 32)->nullable()->after('billing_interval_count');
            $table->unsignedBigInteger('setup_fee_minor')->nullable()->after('tax_treatment');
            $table->string('provider_price_id')->nullable()->after('setup_fee_minor');
            $table->boolean('is_purchasable')->default(false)->after('is_public');
            $table->timestamp('pricing_effective_from')->nullable()->after('is_purchasable');
        });

        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->uuid('checkout_request_uuid');
            $table->string('provider', 32);
            $table->string('provider_mode', 16);
            $table->string('provider_order_id')->nullable();
            $table->string('internal_reference', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('description', 500)->nullable();
            $table->string('receipt_reference', 128)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('subscription_activation_completed_at')->nullable();
            $table->foreignId('activated_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('notification_key', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'checkout_request_uuid'], 'payment_orders_checkout_unique');
            $table->unique(['provider', 'provider_mode', 'provider_order_id'], 'payment_orders_provider_order_unique');
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payment_order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_mode', 16);
            $table->string('provider_payment_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('payment_method_category', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('refunded_amount_minor')->default(0);
            $table->string('failure_category', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_mode', 'provider_payment_id'], 'payments_provider_payment_unique');
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('source', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_order_id', 'created_at']);
            $table->index(['payment_id', 'created_at']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 32);
            $table->string('provider_mode', 16);
            $table->string('provider_event_id');
            $table->string('event_type', 128);
            $table->string('status', 32);
            $table->string('event_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'provider_mode', 'provider_event_id'], 'payment_webhook_events_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_orders');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'currency',
                'amount_minor',
                'billing_interval_count',
                'tax_treatment',
                'setup_fee_minor',
                'provider_price_id',
                'is_purchasable',
                'pricing_effective_from',
            ]);
        });
    }
};
