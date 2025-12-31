<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            // Change procx_manual to nullable string to allow empty values
            $table->string('procx_manual', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->enum('procx_manual', ['PROCX', 'MANUAL'])->default('PROCX')->change();
        });
    }
};
