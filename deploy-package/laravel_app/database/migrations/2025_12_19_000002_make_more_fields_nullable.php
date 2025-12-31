<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            // Make nama_barang nullable (only no_pr is required)
            $table->string('nama_barang', 500)->nullable()->change();
            
            // Make other text fields nullable if not already
            $table->string('um', 50)->nullable()->change();
            $table->string('pg', 50)->nullable()->change();
            $table->string('no_po', 50)->nullable()->change();
            $table->string('nama_vendor', 255)->nullable()->change();
            $table->text('keterangan')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->string('nama_barang', 500)->nullable(false)->change();
        });
    }
};
