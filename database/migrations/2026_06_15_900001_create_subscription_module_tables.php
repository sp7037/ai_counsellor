<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('billing_interval', 32)->default('monthly');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'display_order']);
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature', 64);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->string('limit_period', 32)->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('source', 32)->default('manual');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('provider_name', 64)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_status', 64)->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'current_period_ends_at']);
            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'grace_ends_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'sub_events_tenant_created_idx');
            $table->index(['subscription_id', 'created_at'], 'sub_events_sub_created_idx');
        });

        Schema::create('tenant_entitlement_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('feature', 64);
            $table->boolean('enabled')->nullable();
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature']);
        });

        Schema::create('tenant_usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('used_value')->default(0);
            $table->unsignedBigInteger('reserved_value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period_start'], 'usage_counters_unique_idx');
            $table->index(['tenant_id', 'metric', 'period_end']);
        });

        Schema::create('subscription_usage_warnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric', 64);
            $table->unsignedTinyInteger('threshold_percent');
            $table->string('period_key', 32);
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'metric', 'threshold_percent', 'period_key'],
                'sub_usage_warn_unique_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage_warnings');
        Schema::dropIfExists('tenant_usage_counters');
        Schema::dropIfExists('tenant_entitlement_overrides');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
