<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('knowledge_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 200);
            $table->text('body');
            $table->char('content_checksum', 64);
            $table->timestamp('published_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['knowledge_item_id', 'version_number']);
            $table->index(['tenant_id', 'knowledge_item_id']);
        });

        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('knowledge_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('knowledge_versions');
    }
};
