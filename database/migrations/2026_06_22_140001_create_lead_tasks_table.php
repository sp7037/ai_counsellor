<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('task_type', 40)->default('counselling');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 32)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'status']);
            $table->index(['tenant_id', 'assigned_to_user_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'due_at']);
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->string('title')->nullable()->after('action_type');
            $table->text('description')->nullable()->after('title');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'updated_at']);
        });

        Schema::dropIfExists('lead_tasks');
    }
};
