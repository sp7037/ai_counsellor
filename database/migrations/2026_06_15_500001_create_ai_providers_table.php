<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->boolean('supports_tools')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('ai_providers')->insert([
            'slug' => 'openai',
            'name' => 'OpenAI',
            'supports_tools' => false,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
