<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('visitor_id')->constrained()->restrictOnDelete();
            $table->foreignId('widget_key_id')->constrained()->restrictOnDelete();
            $table->string('origin_domain');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['token_hash', 'expires_at']);
            $table->index(['tenant_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_sessions');
    }
};
