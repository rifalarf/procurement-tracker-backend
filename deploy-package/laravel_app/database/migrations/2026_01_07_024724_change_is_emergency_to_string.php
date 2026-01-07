<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First change the column type to string
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->string('is_emergency', 50)->nullable()->change();
        });
        
        // Then convert existing values: '1' -> 'YES', '0' or null -> null
        DB::statement("UPDATE procurement_items SET is_emergency = CASE WHEN is_emergency = '1' THEN 'YES' ELSE NULL END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert string back to boolean-like values
        DB::statement("UPDATE procurement_items SET is_emergency = CASE WHEN UPPER(is_emergency) = 'YES' THEN '1' ELSE '0' END");
        
        // Change column back to boolean
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->boolean('is_emergency')->default(false)->change();
        });
    }
};
