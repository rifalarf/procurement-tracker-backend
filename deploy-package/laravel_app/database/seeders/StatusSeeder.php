<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        // Core statuses (active) - in workflow order
        // These match config/procurement_flow.php core_statuses_in_order
        $coreStatuses = [
            ['name' => 'DUR', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'RFQ', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Bid Open', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Evaluasi Teknis & Komersial', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Negosiasi', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Persetujuan Pemenang', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 6, 'is_active' => true],
            ['name' => 'Pengumuman Pemenang', 'bg_color' => '#d1fae5', 'text_color' => '#065f46', 'sort_order' => 7, 'is_active' => true],
            ['name' => 'PO / SPK', 'bg_color' => '#bfdbfe', 'text_color' => '#1e3a8a', 'sort_order' => 8, 'is_active' => true],
            ['name' => 'Selesai', 'bg_color' => '#bbf7d0', 'text_color' => '#14532d', 'sort_order' => 9, 'is_active' => true],
            ['name' => 'Batal', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 10, 'is_active' => true],
        ];

        // Legacy statuses (inactive) - kept for FK integrity
        $legacyStatuses = [
            ['name' => 'Konfirmasi Spesifikasi', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 101, 'is_active' => false],
            ['name' => 'Konfirmasi Anggaran', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 102, 'is_active' => false],
            ['name' => 'App. Nego', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 103, 'is_active' => false],
            ['name' => 'Auction', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 104, 'is_active' => false],
            ['name' => 'SPK', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 105, 'is_active' => false],
            ['name' => 'TTD SPK', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 106, 'is_active' => false],
            ['name' => 'Approval PO', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 107, 'is_active' => false],
            ['name' => 'PO', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 108, 'is_active' => false],
            ['name' => 'TTD PO', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 109, 'is_active' => false],
            ['name' => 'LOI/Belum PO', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 110, 'is_active' => false],
            ['name' => 'Rebid', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 111, 'is_active' => false],
            ['name' => 'Retender', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 112, 'is_active' => false],
            ['name' => 'PR Dibatalkan', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 113, 'is_active' => false],
            ['name' => 'PO dibatalkan', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 114, 'is_active' => false],
            ['name' => 'Dibatalkan', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 115, 'is_active' => false],
            ['name' => 'PR dikembalikan ke PPP untuk di proses di PI', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 116, 'is_active' => false],
            ['name' => 'Draft', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 117, 'is_active' => false],
            ['name' => 'Pending', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 118, 'is_active' => false],
            ['name' => 'Approved', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 119, 'is_active' => false],
            ['name' => 'Rejected', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 120, 'is_active' => false],
            // Old status names (before refactoring)
            ['name' => 'Persetujuan Pemenang', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 121, 'is_active' => false],
            ['name' => 'Awarding', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 122, 'is_active' => false],
            ['name' => 'PO/SPK', 'bg_color' => '#bfdbfe', 'text_color' => '#1e3a8a', 'sort_order' => 123, 'is_active' => false],
        ];

        // Merge all statuses
        $allStatuses = array_merge($coreStatuses, $legacyStatuses);

        foreach ($allStatuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}