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
        'Awarding',
        'PO/SPK',
        'Selesai',
        'Dibatalkan',
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
        'Dibatalkan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Eligibility Actions
    |--------------------------------------------------------------------------
    |
    | Defines which statuses allow each exception action.
    | - rebid: Restart bidding process
    | - retender: Full re-tender from scratch
    | - cancel: Cancel the procurement item
    |
    */
    'eligibility_actions' => [
        'rebid' => [
            'RFQ',
            'Bid Open',
        ],
        'retender' => [
            'Bid Open',
            'Evaluasi Teknis & Komersial',
            'Negosiasi',
            'Persetujuan Pemenang',
            'Awarding',
        ],
        'cancel' => [
            'DUR',
            'RFQ',
            'Bid Open',
            'Evaluasi Teknis & Komersial',
            'Negosiasi',
            'Persetujuan Pemenang',
            'Awarding',
            'PO/SPK',
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
        'Konfirmasi Spesifikasi' => 'Evaluasi Teknis & Komersial',
        'Konfirmasi Anggaran' => 'Evaluasi Teknis & Komersial',
        'App. Nego' => 'Negosiasi',
        'Auction' => 'Negosiasi',
        'Approval PO' => 'PO/SPK',
        'PO' => 'PO/SPK',
        'TTD PO' => 'PO/SPK',
        'LOI/Belum PO' => 'PO/SPK',
        'SPK' => 'PO/SPK',
        'TTD SPK' => 'PO/SPK',
        'Rebid' => 'RFQ',
        'PR Dibatalkan' => 'Dibatalkan',
        'PO dibatalkan' => 'Dibatalkan',
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
        'REBID',      // Rebid action
        'RETENDER',   // Retender action
        'CANCEL',     // Cancel action
        'MIGRATION',  // Data migration
    ],
];
