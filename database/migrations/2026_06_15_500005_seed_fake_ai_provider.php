<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_providers')->where('slug', 'fake')->doesntExist()) {
            DB::table('ai_providers')->insert([
                'slug' => 'fake',
                'name' => 'Fake (tests)',
                'supports_tools' => false,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ai_providers')->where('slug', 'fake')->delete();
    }
};
