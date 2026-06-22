<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('import_type');
            $table->string('original_filename');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('knowledge_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_import_id')->constrained('knowledge_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['knowledge_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_import_rows');
        Schema::dropIfExists('knowledge_imports');
    }
};
