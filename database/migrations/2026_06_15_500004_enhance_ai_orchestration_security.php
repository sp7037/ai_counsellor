<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable()->after('uuid');
            $table->unique(['tenant_id', 'conversation_id', 'request_uuid'], 'messages_tenant_conversation_request_uuid_unique');
        });

        Schema::table('tenant_ai_configs', function (Blueprint $table) {
            $table->string('credential_mode', 64)
                ->default('platform_managed')
                ->after('enabled');
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropUnique(['request_uuid']);
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->foreignId('triggering_message_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('messages')
                ->restrictOnDelete();
            $table->string('credential_source', 32)->nullable()->after('model');
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('error_category');

            $table->unique(['tenant_id', 'request_uuid'], 'ai_runs_tenant_request_uuid_unique');
            $table->index(['triggering_message_id', 'status'], 'ai_runs_triggering_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropIndex('ai_runs_triggering_status_index');
            $table->dropUnique('ai_runs_tenant_request_uuid_unique');
            $table->dropConstrainedForeignId('triggering_message_id');
            $table->dropColumn(['credential_source', 'attempt_number']);
            $table->unique('request_uuid');
        });

        Schema::table('tenant_ai_configs', function (Blueprint $table) {
            $table->dropColumn('credential_mode');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_tenant_conversation_request_uuid_unique');
            $table->dropColumn('request_uuid');
        });
    }
};
