<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_widget_settings', function (Blueprint $table) {
            $table->string('launcher_mode')->default('circle')->after('welcome_delay_seconds');
            $table->string('launcher_card_image_path')->nullable()->after('launcher_mode');
            $table->string('launcher_card_title')->nullable()->after('launcher_card_image_path');
            $table->text('launcher_card_subtitle')->nullable()->after('launcher_card_title');
            $table->string('launcher_card_cta_text')->nullable()->after('launcher_card_subtitle');
            $table->string('launcher_card_trust_text')->nullable()->after('launcher_card_cta_text');
            $table->unsignedSmallInteger('launcher_card_delay_seconds')->nullable()->after('launcher_card_trust_text');
            $table->unsignedSmallInteger('launcher_card_dismiss_hours')->nullable()->after('launcher_card_delay_seconds');
            $table->string('launcher_card_animation')->nullable()->after('launcher_card_dismiss_hours');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_widget_settings', function (Blueprint $table) {
            $table->dropColumn([
                'launcher_mode',
                'launcher_card_image_path',
                'launcher_card_title',
                'launcher_card_subtitle',
                'launcher_card_cta_text',
                'launcher_card_trust_text',
                'launcher_card_delay_seconds',
                'launcher_card_dismiss_hours',
                'launcher_card_animation',
            ]);
        });
    }
};
