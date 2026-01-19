<?php

namespace Database\Seeders;

use App\Models\FieldPermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * All procurement item fields
     */
    private const ALL_FIELDS = [
        'no_pr',
        'mat_code',
        'nama_barang',
        'item_category',
        'qty',
        'um',
        'pg',
        'user_requester',
        'nilai',
        'department_id',
        'tgl_terima_dokumen',
        'procx_manual',
        'buyer_id',
        'status_id',
        'tgl_status',
        'is_emergency',
        'no_po',
        'nama_vendor',
        'tgl_po',
        'tgl_datang',
        'keterangan',
    ];

    /**
     * Fields that buyer CAN edit
     */
    private const BUYER_EDITABLE_FIELDS = [
        'procx_manual',
        'buyer_id',
        'status_id',
        'tgl_status',
        'is_emergency',
        'no_po',
        'nama_vendor',
        'tgl_po',
        'tgl_datang',
        'keterangan',
    ];

    /**
     * Fields that AVP can edit (limited)
     */
    private const AVP_EDITABLE_FIELDS = [
        'pg',
        'status_id',
        'tgl_status',
        'keterangan',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing permissions
        FieldPermission::truncate();
        FieldPermission::clearCache();

        // Admin can view and edit all fields
        foreach (self::ALL_FIELDS as $field) {
            FieldPermission::create([
                'role' => 'admin',
                'field_name' => $field,
                'can_view' => true,
                'can_edit' => true,
            ]);
        }

        // Staff can view and edit all fields
        foreach (self::ALL_FIELDS as $field) {
            FieldPermission::create([
                'role' => 'staff',
                'field_name' => $field,
                'can_view' => true,
                'can_edit' => true,
            ]);
        }

        // AVP can view all but edit only specific fields
        foreach (self::ALL_FIELDS as $field) {
            FieldPermission::create([
                'role' => 'avp',
                'field_name' => $field,
                'can_view' => true,
                'can_edit' => in_array($field, self::AVP_EDITABLE_FIELDS),
            ]);
        }

        // Buyer can view all but edit only specific fields
        foreach (self::ALL_FIELDS as $field) {
            FieldPermission::create([
                'role' => 'buyer',
                'field_name' => $field,
                'can_view' => true,
                'can_edit' => in_array($field, self::BUYER_EDITABLE_FIELDS),
            ]);
        }

        $this->command->info('Field permissions seeded successfully!');
        $this->command->table(
            ['Role', 'Editable Fields'],
            [
                ['admin', 'All fields'],
                ['staff', 'All fields'],
                ['avp', implode(', ', self::AVP_EDITABLE_FIELDS)],
                ['buyer', implode(', ', self::BUYER_EDITABLE_FIELDS)],
            ]
        );
    }
}
