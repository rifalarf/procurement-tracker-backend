<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_session_id')->constrained()->onDelete('cascade');
            $table->string('excel_column');
            $table->string('database_field')->nullable();
            $table->string('sample_data', 500)->nullable();
            $table->integer('confidence_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
    }
};
