<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Rename the active 'Persetujuan Pemenang' status to 'Awarding'.
     */
    public function up(): void
    {
        // Step 1: Delete the old inactive 'Awarding' entry to avoid unique constraint
        DB::table('statuses')
            ->where('name', 'Awarding')
            ->where('is_active', false)
            ->delete();

        // Step 2: Rename the active core status 'Persetujuan Pemenang' to 'Awarding'
        DB::table('statuses')
            ->where('name', 'Persetujuan Pemenang')
            ->where('is_active', true)
            ->update(['name' => 'Awarding']);

        // The old inactive 'Persetujuan Pemenang' (sort_order 121) stays for FK integrity
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back from 'Awarding' to 'Persetujuan Pemenang'
        DB::table('statuses')
            ->where('name', 'Awarding')
            ->where('is_active', true)
            ->update(['name' => 'Persetujuan Pemenang']);

        // Re-create the inactive 'Awarding' entry
        DB::table('statuses')->insert([
            'name' => 'Awarding',
            'bg_color' => '#dcfce7',
            'text_color' => '#166534',
            'sort_order' => 122,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
