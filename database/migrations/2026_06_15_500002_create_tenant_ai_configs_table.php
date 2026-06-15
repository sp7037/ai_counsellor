<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_ai_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers')->restrictOnDelete();
            $table->string('model', 120);
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedInteger('max_output_tokens')->default(400);
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->boolean('enabled')->default(true);
            $table->text('encrypted_api_key')->nullable();
            $table->timestamp('secret_updated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['tenant_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_ai_configs');
    }
};
