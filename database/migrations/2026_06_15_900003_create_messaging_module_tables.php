<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_messaging_integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16)->default('test');
            $table->string('status', 32)->default('disabled');
            $table->string('phone_number_id')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('display_phone_number', 32)->nullable();
            $table->string('business_display_name')->nullable();
            $table->string('verify_token', 128);
            $table->json('access_token')->nullable();
            $table->json('app_secret')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_outbound_success_at')->nullable();
            $table->string('last_error_category', 64)->nullable();
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'phone_number_id'], 'messaging_integrations_phone_unique');
            $table->index(['provider', 'status']);
        });

        Schema::create('messaging_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('messaging_integration_id')->constrained('tenant_messaging_integrations')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('external_contact_id', 64);
            $table->string('display_phone', 32)->nullable();
            $table->string('display_name')->nullable();
            $table->string('provider_contact_id')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();

            $table->unique(['messaging_integration_id', 'external_contact_id'], 'messaging_contacts_unique');
            $table->index(['tenant_id', 'channel']);
        });

        Schema::create('messaging_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('messaging_integration_id')->constrained('tenant_messaging_integrations')->cascadeOnDelete();
            $table->string('provider_template_name');
            $table->string('language_code', 16)->default('en');
            $table->string('category', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('variable_definitions')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['messaging_integration_id', 'provider_template_name', 'language_code'],
                'messaging_templates_unique',
            );
        });

        Schema::create('messaging_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 32);
            $table->string('provider_event_id');
            $table->string('event_type', 128)->nullable();
            $table->string('status', 32);
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'provider_event_id'], 'messaging_webhook_events_unique');
        });

        Schema::create('messaging_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('messaging_integration_id')->nullable()->constrained('tenant_messaging_integrations')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('external_reference')->nullable();
            $table->string('processing_status', 32)->default('recorded');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['messaging_integration_id', 'created_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('messaging_integration_id')->nullable()->after('visitor_id')->constrained('tenant_messaging_integrations')->nullOnDelete();
            $table->foreignId('messaging_contact_id')->nullable()->after('messaging_integration_id')->constrained('messaging_contacts')->nullOnDelete();
            $table->string('external_channel_reference', 128)->nullable()->after('messaging_contact_id');
            $table->string('last_inbound_provider_message_id')->nullable()->after('external_channel_reference');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->string('direction', 16)->nullable()->after('role');
            $table->string('provider_message_id')->nullable()->after('direction');
            $table->string('delivery_state', 32)->nullable()->after('provider_message_id');
            $table->string('template_name', 128)->nullable()->after('delivery_state');
            $table->string('reply_to_provider_message_id')->nullable()->after('template_name');
            $table->string('delivery_failure_category', 64)->nullable()->after('reply_to_provider_message_id');

            $table->unique(['tenant_id', 'provider_message_id'], 'messages_provider_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique('messages_provider_message_unique');
            $table->dropColumn([
                'direction',
                'provider_message_id',
                'delivery_state',
                'template_name',
                'reply_to_provider_message_id',
                'delivery_failure_category',
            ]);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('messaging_contact_id');
            $table->dropConstrainedForeignId('messaging_integration_id');
            $table->dropColumn(['external_channel_reference', 'last_inbound_provider_message_id']);
        });

        Schema::dropIfExists('messaging_events');
        Schema::dropIfExists('messaging_webhook_events');
        Schema::dropIfExists('messaging_templates');
        Schema::dropIfExists('messaging_contacts');
        Schema::dropIfExists('tenant_messaging_integrations');
    }
};
