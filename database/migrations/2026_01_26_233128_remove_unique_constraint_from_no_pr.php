<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remove unique constraint from no_pr column to allow multiple items
     * with the same NO PR (e.g., PR-001 with Laptop, PR-001 with Mac Mini)
     */
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            // Drop the unique index on no_pr
            $table->dropUnique('procurement_items_no_pr_unique');
            
            // Add a regular index for faster lookups (no_pr is still used for querying)
            $table->index('no_pr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            // Remove regular index
            $table->dropIndex(['no_pr']);
            
            // Restore unique constraint
            $table->unique('no_pr');
        });
    }
};
