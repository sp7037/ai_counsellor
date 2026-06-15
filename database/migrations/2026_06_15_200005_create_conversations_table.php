<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('visitor_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('channel')->default('widget');
            $table->string('status')->default('open');
            $table->string('source_url', 2048)->nullable();
            $table->string('origin_domain')->nullable();
            $table->string('locale', 12)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'visitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
