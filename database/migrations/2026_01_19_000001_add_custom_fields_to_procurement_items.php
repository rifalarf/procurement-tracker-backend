<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->string('custom_field_1', 500)->nullable()->after('keterangan');
            $table->string('custom_field_2', 500)->nullable()->after('custom_field_1');
            $table->string('custom_field_3', 500)->nullable()->after('custom_field_2');
            $table->string('custom_field_4', 500)->nullable()->after('custom_field_3');
            $table->string('custom_field_5', 500)->nullable()->after('custom_field_4');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropColumn([
                'custom_field_1',
                'custom_field_2',
                'custom_field_3',
                'custom_field_4',
                'custom_field_5',
            ]);
        });
    }
};
