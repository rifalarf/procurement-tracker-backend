<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, delete the old legacy 'Persetujuan Pemenang' if it exists
        DB::table('statuses')
            ->where('name', 'Persetujuan Pemenang')
            ->where('is_active', false)
            ->delete();

        // Then update 'Persetujuan Pemenang / Awarding' to 'Persetujuan Pemenang'
        DB::table('statuses')
            ->where('name', 'Persetujuan Pemenang / Awarding')
            ->update([
                'name' => 'Persetujuan Pemenang',
                'is_active' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to old name
        DB::table('statuses')
            ->where('name', 'Persetujuan Pemenang')
            ->where('sort_order', 6)
            ->update([
                'name' => 'Persetujuan Pemenang / Awarding',
            ]);
    }
};
