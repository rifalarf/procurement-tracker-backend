<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'DUR', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 1], // Gray
            ['name' => 'RFQ', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 2], // Orange
            ['name' => 'Bid Open', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 3], // Yellow
            ['name' => 'Evaluasi Teknis & Komersial', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 4], // Blue
            ['name' => 'Konfirmasi Spesifikasi', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 5], // Blue
            ['name' => 'Konfirmasi Anggaran', 'bg_color' => '#dbeafe', 'text_color' => '#1e40af', 'sort_order' => 6], // Blue
            ['name' => 'Negosiasi', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 7], // Purple
            ['name' => 'App. Nego', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 8], // Purple
            ['name' => 'Auction', 'bg_color' => '#f3e8ff', 'text_color' => '#6b21a8', 'sort_order' => 9], // Purple
            ['name' => 'Persetujuan Pemenang', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 10], // Green
            ['name' => 'Awarding', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 11], // Green
            ['name' => 'SPK', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 12], // Gray
            ['name' => 'TTD SPK', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 13], // Green
            ['name' => 'Approval PO', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 14], // Orange
            ['name' => 'PO', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 15], // Green
            ['name' => 'TTD PO', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 16], // Green
            ['name' => 'LOI/Belum PO', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 17], // Yellow
            ['name' => 'Rebid', 'bg_color' => '#ffedd5', 'text_color' => '#9a3412', 'sort_order' => 18], // Orange
            ['name' => 'PR Dibatalkan', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 19], // Red
            ['name' => 'PO dibatalkan', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 20], // Red
            ['name' => 'PR dikembalikan ke PPP untuk di proses di PI', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 21], // Red
            
            // Standard simple statuses if needed
            ['name' => 'Draft', 'bg_color' => '#f1f5f9', 'text_color' => '#334155', 'sort_order' => 90],
            ['name' => 'Pending', 'bg_color' => '#fef9c3', 'text_color' => '#854d0e', 'sort_order' => 91],
            ['name' => 'Approved', 'bg_color' => '#dcfce7', 'text_color' => '#166534', 'sort_order' => 92],
            ['name' => 'Rejected', 'bg_color' => '#fee2e2', 'text_color' => '#991b1b', 'sort_order' => 93],
        ];

        foreach ($statuses as $index => $status) {
            Status::updateOrCreate(
                ['name' => $status['name']],
                array_merge($status, ['sort_order' => $index + 1])
            );
        }
    }
}