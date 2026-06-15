<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('mode', 32)->default('ai')->after('status');
            $table->foreignId('human_owner_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            $table->foreignId('target_counsellor_id')->nullable()->after('human_owner_id')->constrained('users')->nullOnDelete();
            $table->uuid('handoff_request_uuid')->nullable()->after('target_counsellor_id');
            $table->timestamp('handoff_requested_at')->nullable()->after('handoff_request_uuid');
            $table->timestamp('human_takeover_at')->nullable()->after('handoff_requested_at');
            $table->timestamp('human_released_at')->nullable()->after('human_takeover_at');
            $table->string('close_reason')->nullable()->after('closed_at');
            $table->timestamp('last_visitor_message_at')->nullable()->after('last_message_at');
            $table->timestamp('last_human_message_at')->nullable()->after('last_visitor_message_at');
            $table->unsignedInteger('counsellor_unread_count')->default(0)->after('last_human_message_at');
            $table->foreignId('visitor_last_read_message_id')->nullable()->after('counsellor_unread_count')->constrained('messages')->nullOnDelete();

            $table->unique(['tenant_id', 'handoff_request_uuid'], 'conversations_tenant_handoff_request_unique');
            $table->index(['tenant_id', 'mode']);
            $table->index(['tenant_id', 'human_owner_id', 'mode']);
            $table->index(['tenant_id', 'target_counsellor_id', 'mode']);
            $table->index(['tenant_id', 'handoff_requested_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_user_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->string('sender_display_name', 120)->nullable()->after('sender_user_id');
            $table->index(['conversation_id', 'id']);
        });

        Schema::table('counsellor_profiles', function (Blueprint $table) {
            $table->string('availability', 20)->default('available')->after('timezone');
        });

        Schema::table('lead_notifications', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->string('purpose', 32)->default('response')->after('status');
        });

        Schema::create('conversation_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('counsellor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->boolean('is_current')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id', 'is_current']);
            $table->index(['tenant_id', 'counsellor_id', 'is_current']);
        });

        Schema::create('conversation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 40);
            $table->json('metadata')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'conversation_id', 'created_at'], 'conv_activities_tenant_conv_created_idx');
        });

        Schema::create('conversation_read_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_read_states');
        Schema::dropIfExists('conversation_activities');
        Schema::dropIfExists('conversation_handoffs');

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });

        Schema::table('lead_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::table('counsellor_profiles', function (Blueprint $table) {
            $table->dropColumn('availability');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropConstrainedForeignId('sender_user_id');
            $table->dropColumn('sender_display_name');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['visitor_last_read_message_id']);
            $table->dropForeign(['human_owner_id']);
            $table->dropForeign(['target_counsellor_id']);
            $table->dropUnique('conversations_tenant_handoff_request_unique');
            $table->dropIndex(['tenant_id', 'mode']);
            $table->dropIndex(['tenant_id', 'human_owner_id', 'mode']);
            $table->dropIndex(['tenant_id', 'target_counsellor_id', 'mode']);
            $table->dropIndex(['tenant_id', 'handoff_requested_at']);
            $table->dropColumn([
                'mode',
                'human_owner_id',
                'target_counsellor_id',
                'handoff_request_uuid',
                'handoff_requested_at',
                'human_takeover_at',
                'human_released_at',
                'close_reason',
                'last_visitor_message_at',
                'last_human_message_at',
                'counsellor_unread_count',
                'visitor_last_read_message_id',
            ]);
        });
    }
};
