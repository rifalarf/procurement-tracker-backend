<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->index('custom_field_1');
            $table->index('custom_field_2');
            $table->index('mat_code');
            $table->index('tgl_terima_dokumen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropIndex(['custom_field_1']);
            $table->dropIndex(['custom_field_2']);
            $table->dropIndex(['mat_code']);
            $table->dropIndex(['tgl_terima_dokumen']);
        });
    }
};
