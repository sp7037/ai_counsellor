<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('original_slug')->nullable()->after('slug');
            $table->string('original_email')->nullable()->after('email');
            $table->boolean('identifier_restore_conflict')->default(false)->after('delete_reason');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('original_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['original_slug', 'original_email', 'identifier_restore_conflict']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('original_email');
        });
    }
};
