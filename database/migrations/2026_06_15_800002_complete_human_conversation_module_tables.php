<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_read_states')) {
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

        if (Schema::hasTable('conversation_activities') && ! $this->indexExists('conversation_activities', 'conv_activities_tenant_conv_created_idx')) {
            Schema::table('conversation_activities', function (Blueprint $table) {
                $table->index(['tenant_id', 'conversation_id', 'created_at'], 'conv_activities_tenant_conv_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_read_states');

        if ($this->indexExists('conversation_activities', 'conv_activities_tenant_conv_created_idx')) {
            Schema::table('conversation_activities', function (Blueprint $table) {
                $table->dropIndex('conv_activities_tenant_conv_created_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition) => ($definition['name'] ?? '') === $index);
    }
};
