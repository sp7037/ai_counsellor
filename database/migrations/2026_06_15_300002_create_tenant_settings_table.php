<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name', 120)->nullable();
            $table->string('assistant_name', 120)->nullable();
            $table->string('assistant_title', 120)->nullable();
            $table->char('primary_color', 7)->default('#2563EB');
            $table->char('accent_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('consent_text')->nullable();
            $table->string('consent_version', 32)->nullable();
            $table->boolean('ai_disclosure_enabled')->default(true);
            $table->string('ai_disclosure_message', 500)->nullable();
            $table->string('default_locale', 12)->default('en');
            $table->json('supported_locales')->nullable();
            $table->boolean('human_transfer_enabled')->default(true);
            $table->string('human_transfer_label', 120)->default('Speak to a counsellor');
            $table->string('human_transfer_message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
