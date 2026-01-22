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
        Schema::create('column_configs', function (Blueprint $table) {
            $table->id();
            $table->string('field_name', 50)->unique();
            $table->string('label', 100)->nullable();
            $table->integer('display_order')->default(0);
            $table->string('width', 20)->default('auto'); // auto, sm, md, lg, xl, or fixed (80px, 120px, etc)
            $table->boolean('is_visible_in_table')->default(true);
            $table->boolean('is_visible_in_detail')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed default configurations for all columns
        $now = now();
        $defaultColumns = [
            // Visible in table by default
            ['field_name' => 'no_pr', 'label' => 'No PR', 'display_order' => 1, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'mat_code', 'label' => 'Mat Code', 'display_order' => 2, 'width' => 'sm', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'nama_barang', 'label' => 'Nama Barang', 'display_order' => 3, 'width' => 'xl', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_terima_dokumen', 'label' => 'Tgl Terima Dok', 'display_order' => 4, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'qty', 'label' => 'Qty', 'display_order' => 5, 'width' => 'sm', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'nilai', 'label' => 'Nilai', 'display_order' => 6, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'department_id', 'label' => 'Bagian', 'display_order' => 7, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'buyer_id', 'label' => 'Buyer', 'display_order' => 8, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'user_requester', 'label' => 'User', 'display_order' => 9, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'status_id', 'label' => 'Status', 'display_order' => 10, 'width' => 'md', 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            // Hidden in table by default, visible in detail
            ['field_name' => 'item_category', 'label' => 'Item Category', 'display_order' => 11, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'um', 'label' => 'UM', 'display_order' => 12, 'width' => 'sm', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'pg', 'label' => 'PG', 'display_order' => 13, 'width' => 'sm', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'procx_manual', 'label' => 'PROCX/MANUAL', 'display_order' => 14, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_status', 'label' => 'Tanggal Status', 'display_order' => 15, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'is_emergency', 'label' => 'Emergency', 'display_order' => 16, 'width' => 'sm', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'no_po', 'label' => 'No PO', 'display_order' => 17, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'nama_vendor', 'label' => 'Nama Vendor', 'display_order' => 18, 'width' => 'lg', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_po', 'label' => 'Tanggal PO', 'display_order' => 19, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_datang', 'label' => 'Tanggal Datang', 'display_order' => 20, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'keterangan', 'label' => 'Keterangan', 'display_order' => 21, 'width' => 'xl', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            // Custom fields (visibility based on CustomFieldConfig)
            ['field_name' => 'custom_field_1', 'label' => 'Custom 1', 'display_order' => 22, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_2', 'label' => 'Custom 2', 'display_order' => 23, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_3', 'label' => 'Custom 3', 'display_order' => 24, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_4', 'label' => 'Custom 4', 'display_order' => 25, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_5', 'label' => 'Custom 5', 'display_order' => 26, 'width' => 'md', 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
        ];

        foreach ($defaultColumns as $column) {
            DB::table('column_configs')->insert(array_merge($column, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_configs');
    }
};
