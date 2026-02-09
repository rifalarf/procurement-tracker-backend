<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Core Statuses in Order
    |--------------------------------------------------------------------------
    |
    | The main procurement statuses in their logical flow order.
    | These are the only statuses that should be active (is_active=true).
    | The "advance" action moves items through this sequence.
    |
    */
    'core_statuses_in_order' => [
        'DUR',
        'RFQ',
        'Bid Open',
        'Evaluasi Teknis & Komersial',
        'Negosiasi',
        'Persetujuan Pemenang',
        'Pengumuman Pemenang',
        'PO / SPK',
        'Selesai',
        'Batal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal Statuses
    |--------------------------------------------------------------------------
    |
    | Statuses that end the workflow. Items in these statuses cannot be
    | advanced further.
    |
    */
    'terminal' => [
        'Selesai',
        'Batal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Eligibility Actions
    |--------------------------------------------------------------------------
    |
    | Defines which statuses allow each exception action.
    | - rebid: Restart process from DUR
    | - cancel: Cancel the procurement item
    |
    */
    'eligibility_actions' => [
        'rebid' => [
            'RFQ',
            'Bid Open',
            'Evaluasi Teknis & Komersial',
            'Negosiasi',
            'Persetujuan Pemenang',
            'Pengumuman Pemenang',
        ],
        'cancel' => [
            'DUR',
            'RFQ',
            'Bid Open',
            'Evaluasi Teknis & Komersial',
            'Negosiasi',
            'Persetujuan Pemenang',
            'Pengumuman Pemenang',
            'PO / SPK',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Status Mapping
    |--------------------------------------------------------------------------
    |
    | Maps old/legacy status names to their new core status equivalents.
    | Used during data migration and import.
    |
    */
    'legacy_status_mapping' => [
        // Evaluation phase mappings
        'Konfirmasi Spesifikasi' => 'Evaluasi Teknis & Komersial',
        'Konfirmasi Anggaran' => 'Evaluasi Teknis & Komersial',

        // Negotiation phase mappings
        'App. Nego' => 'Negosiasi',
        'Auction' => 'Negosiasi',

        // Awarding phase mappings
        'Awarding' => 'Persetujuan Pemenang',
        'Persetujuan Pemenang' => 'Persetujuan Pemenang',

        // PO phase mappings
        'Approval PO' => 'PO / SPK',
        'PO' => 'PO / SPK',
        'TTD PO' => 'PO / SPK',
        'LOI/Belum PO' => 'PO / SPK',
        'SPK' => 'PO / SPK',
        'TTD SPK' => 'PO / SPK',
        'PO/SPK' => 'PO / SPK',

        // Rebid/Retender → DUR
        'Rebid' => 'DUR',
        'Retender' => 'DUR',

        // Cancelled status mappings
        'PR Dibatalkan' => 'Batal',
        'PO dibatalkan' => 'Batal',
        'Dibatalkan' => 'Batal',

        // Return to DUR
        'PR dikembalikan ke PPP untuk di proses di PI' => 'DUR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Types
    |--------------------------------------------------------------------------
    |
    | Allowed event types for status_histories.event_type column.
    |
    */
    'event_types' => [
        'MANUAL',     // Manual status change (admin override)
        'ADVANCE',    // Normal flow progression
        'REBID',      // Rebid action (return to DUR)
        'CANCEL',     // Cancel action
        'MIGRATION',  // Data migration
    ],
];
