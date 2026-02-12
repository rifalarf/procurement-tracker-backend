<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Changes buyer_id foreign key from users table to buyers table.
     * This includes cleaning up buyer_id values to match buyers table.
     */
    public function up(): void
    {
        if (!Schema::hasTable('procurement_items')) {
            return;
        }

        // Check existing FK constraint
        $connection = Schema::getConnection();
        $hasFk = false;
        $fkReferencesTable = null;

        try {
            $foreignKeys = $connection
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys('procurement_items');

            foreach ($foreignKeys as $fk) {
                if (method_exists($fk, 'getName') && $fk->getName() === 'procurement_items_buyer_id_foreign') {
                    $hasFk = true;
                    $fkReferencesTable = method_exists($fk, 'getForeignTableName') ? $fk->getForeignTableName() : null;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // On drivers that cannot introspect (or Doctrine missing), skip safely
        }

        // If FK already correctly references buyers table (fresh install), skip everything
        if ($hasFk && $fkReferencesTable === 'buyers') {
            return;
        }

        // Step 1: Drop existing FK if present (it points to wrong table)
        if ($hasFk) {
            Schema::table('procurement_items', function (Blueprint $table) {
                $table->dropForeign(['buyer_id']);
            });
        }

        // Step 2: Map old buyer_id (from users table) to new buyer_id (from buyers table)
        $buyers = DB::table('buyers')
            ->whereNotNull('user_id')
            ->get(['id as buyer_id', 'user_id']);

        foreach ($buyers as $buyer) {
            DB::table('procurement_items')
                ->where('buyer_id', $buyer->user_id)
                ->update(['buyer_id' => $buyer->buyer_id]);
        }

        // Step 3: Set any buyer_id values that don't exist in buyers table to NULL
        $validBuyerIds = DB::table('buyers')->pluck('id')->toArray();
        if (!empty($validBuyerIds)) {
            DB::table('procurement_items')
                ->whereNotNull('buyer_id')
                ->whereNotIn('buyer_id', $validBuyerIds)
                ->update(['buyer_id' => null]);
        }

        // Step 4: Add new foreign key constraint to buyers table
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->foreign('buyer_id')
                ->references('id')
                ->on('buyers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('procurement_items')) {
            return;
        }

        // Remove the buyers FK if exists
        $connection = Schema::getConnection();
        $hasFk = false;

        try {
            $foreignKeys = $connection
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys('procurement_items');

            $hasFk = collect($foreignKeys)
                ->contains(function ($fk) {
                    return method_exists($fk, 'getName')
                        && $fk->getName() === 'procurement_items_buyer_id_foreign';
                });
        } catch (\Throwable $e) {
            // Skip drop if introspection fails
        }

        if ($hasFk) {
            Schema::table('procurement_items', function (Blueprint $table) {
                $table->dropForeign(['buyer_id']);
            });
        }

        // Note: We can't fully reverse the data mapping, 
        // so we just restore the FK to users table
        // The buyer_id values would need manual correction

        Schema::table('procurement_items', function (Blueprint $table) {
            $table->foreign('buyer_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }
};
