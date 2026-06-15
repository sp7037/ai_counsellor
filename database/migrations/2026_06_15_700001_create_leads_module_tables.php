<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_reference', 32);
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 40);
            $table->string('source_reference', 120)->nullable();
            $table->uuid('capture_event_uuid')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_contact_method', 32)->nullable();
            $table->string('location')->nullable();
            $table->string('state', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('service_interest')->nullable();
            $table->text('programme_interest')->nullable();
            $table->text('enquiry_summary')->nullable();
            $table->text('qualification_notes')->nullable();
            $table->unsignedSmallInteger('lead_score')->default(0);
            $table->string('qualification_status', 40)->default('not_reviewed');
            $table->string('stage', 40)->default('new');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->string('invalid_reason')->nullable();
            $table->text('ai_suggested_summary')->nullable();
            $table->unsignedSmallInteger('ai_suggested_score')->nullable();
            $table->string('ai_suggested_priority', 20)->nullable();
            $table->json('score_components')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_reference']);
            $table->unique(['tenant_id', 'capture_event_uuid'], 'leads_tenant_capture_event_unique');
            $table->unique(['tenant_id', 'source', 'source_reference'], 'leads_tenant_source_reference_unique');
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'qualification_status']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'next_follow_up_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'is_current']);
            $table->index(['tenant_id', 'assigned_to', 'is_current']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 40);
            $table->json('metadata')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'lead_id', 'created_at']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id']);
        });

        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 32)->default('scheduled');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_outcome')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'assigned_to', 'due_at']);
            $table->index(['tenant_id', 'status', 'due_at']);
        });

        Schema::create('lead_qualification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->json('rules');
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('counsellor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('tenant_user')->restrictOnDelete();
            $table->string('mobile', 20)->nullable();
            $table->string('designation')->nullable();
            $table->unsignedSmallInteger('max_active_leads')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamps();

            $table->unique('membership_id');
            $table->index(['tenant_id', 'membership_id']);
        });

        Schema::create('lead_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notifications');
        Schema::dropIfExists('counsellor_profiles');
        Schema::dropIfExists('lead_qualification_rules');
        Schema::dropIfExists('lead_follow_ups');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_assignments');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        Schema::dropIfExists('leads');
    }
};
