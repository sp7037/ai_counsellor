<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_widget_settings', function (Blueprint $table) {
            $table->string('widget_position', 32)->default('bottom_right')->after('offline_form_enabled');
            $table->unsignedSmallInteger('welcome_delay_seconds')->default(0)->after('widget_position');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_widget_settings', function (Blueprint $table) {
            $table->dropColumn(['widget_position', 'welcome_delay_seconds']);
        });
    }
};
