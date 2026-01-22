<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ColumnConfig;
use App\Models\CustomFieldConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ColumnConfigController extends Controller
{
    /**
     * Get all column configurations ordered by display_order
     * Admin only
     */
    public function index(): JsonResponse
    {
        $configs = ColumnConfig::ordered()->get();
        $customFieldConfigs = CustomFieldConfig::all()->keyBy('field_name');

        // Enrich with custom field info
        $enrichedConfigs = $configs->map(function ($config) use ($customFieldConfigs) {
            $data = $config->toArray();
            $data['is_custom_field'] = $config->isCustomField();

            // For custom fields, check if they are active in CustomFieldConfig
            if ($config->isCustomField()) {
                $customConfig = $customFieldConfigs->get($config->field_name);
                $data['custom_field_active'] = $customConfig ? $customConfig->is_active : false;
                // Use custom field label if ColumnConfig label is empty
                if (empty($config->label) && $customConfig && $customConfig->label) {
                    $data['label'] = $customConfig->label;
                }
            }

            return $data;
        });

        return response()->json(['data' => $enrichedConfigs]);
    }

    /**
     * Get columns visible in table
     * All authenticated users
     */
    public function getTableColumns(): JsonResponse
    {
        $configs = ColumnConfig::visibleInTable()->get();
        $customFieldConfigs = CustomFieldConfig::all()->keyBy('field_name');

        // Filter out inactive custom fields and enrich data
        $filteredConfigs = $configs->filter(function ($config) use ($customFieldConfigs) {
            if ($config->isCustomField()) {
                $customConfig = $customFieldConfigs->get($config->field_name);
                return $customConfig && $customConfig->is_active;
            }
            return true;
        })->map(function ($config) use ($customFieldConfigs) {
            $data = $config->toArray();
            $data['is_custom_field'] = $config->isCustomField();

            // Use custom field label if ColumnConfig label is empty
            if ($config->isCustomField()) {
                $customConfig = $customFieldConfigs->get($config->field_name);
                if (empty($config->label) && $customConfig && $customConfig->label) {
                    $data['label'] = $customConfig->label;
                }
            }

            return $data;
        })->values();

        return response()->json(['data' => $filteredConfigs]);
    }

    /**
     * Get columns visible in detail page
     * All authenticated users
     */
    public function getDetailColumns(): JsonResponse
    {
        $configs = ColumnConfig::visibleInDetail()->get();
        $customFieldConfigs = CustomFieldConfig::all()->keyBy('field_name');

        // Filter out inactive custom fields and enrich data
        $filteredConfigs = $configs->filter(function ($config) use ($customFieldConfigs) {
            if ($config->isCustomField()) {
                $customConfig = $customFieldConfigs->get($config->field_name);
                return $customConfig && $customConfig->is_active;
            }
            return true;
        })->map(function ($config) use ($customFieldConfigs) {
            $data = $config->toArray();
            $data['is_custom_field'] = $config->isCustomField();

            // Use custom field label if ColumnConfig label is empty
            if ($config->isCustomField()) {
                $customConfig = $customFieldConfigs->get($config->field_name);
                if (empty($config->label) && $customConfig && $customConfig->label) {
                    $data['label'] = $customConfig->label;
                }
            }

            return $data;
        })->values();

        return response()->json(['data' => $filteredConfigs]);
    }

    /**
     * Bulk update column configurations
     * Admin only
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configs' => 'required|array|min:1',
            'configs.*.id' => 'required|integer|exists:column_configs,id',
            'configs.*.label' => 'nullable|string|max:100',
            'configs.*.display_order' => 'nullable|integer|min:0',
            'configs.*.is_visible_in_table' => 'nullable|boolean',
            'configs.*.is_visible_in_detail' => 'nullable|boolean',
        ]);

        $updatedCount = 0;
        $userId = auth()->id();

        DB::beginTransaction();
        try {
            foreach ($validated['configs'] as $configData) {
                $config = ColumnConfig::find($configData['id']);
                if ($config) {
                    $updateData = ['updated_by' => $userId];

                    if (array_key_exists('label', $configData)) {
                        $updateData['label'] = $configData['label'];
                    }
                    if (array_key_exists('display_order', $configData)) {
                        $updateData['display_order'] = $configData['display_order'];
                    }
                    if (array_key_exists('is_visible_in_table', $configData)) {
                        $updateData['is_visible_in_table'] = $configData['is_visible_in_table'];
                    }
                    if (array_key_exists('is_visible_in_detail', $configData)) {
                        $updateData['is_visible_in_detail'] = $configData['is_visible_in_detail'];
                    }

                    $config->update($updateData);
                    $updatedCount++;
                }
            }
            DB::commit();

            return response()->json([
                'message' => "Berhasil memperbarui {$updatedCount} konfigurasi kolom",
                'updated_count' => $updatedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui konfigurasi kolom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder columns (update display_order only)
     * Admin only
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*.id' => 'required|integer|exists:column_configs,id',
            'columns.*.display_order' => 'required|integer|min:0',
        ]);

        $userId = auth()->id();

        DB::beginTransaction();
        try {
            foreach ($validated['columns'] as $columnData) {
                ColumnConfig::where('id', $columnData['id'])->update([
                    'display_order' => $columnData['display_order'],
                    'updated_by' => $userId,
                ]);
            }
            DB::commit();

            return response()->json([
                'message' => 'Urutan kolom berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui urutan kolom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset all configurations to defaults
     * Admin only
     */
    public function reset(): JsonResponse
    {
        $now = now();
        $userId = auth()->id();

        $defaultColumns = [
            ['field_name' => 'no_pr', 'label' => 'No PR', 'display_order' => 1, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'mat_code', 'label' => 'Mat Code', 'display_order' => 2, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'nama_barang', 'label' => 'Nama Barang', 'display_order' => 3, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_terima_dokumen', 'label' => 'Tgl Terima Dok', 'display_order' => 4, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'qty', 'label' => 'Qty', 'display_order' => 5, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'nilai', 'label' => 'Nilai', 'display_order' => 6, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'department_id', 'label' => 'Bagian', 'display_order' => 7, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'buyer_id', 'label' => 'Buyer', 'display_order' => 8, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'user_requester', 'label' => 'User', 'display_order' => 9, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'status_id', 'label' => 'Status', 'display_order' => 10, 'is_visible_in_table' => true, 'is_visible_in_detail' => true],
            ['field_name' => 'item_category', 'label' => 'Item Category', 'display_order' => 11, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'um', 'label' => 'UM', 'display_order' => 12, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'pg', 'label' => 'PG', 'display_order' => 13, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'procx_manual', 'label' => 'PROCX/MANUAL', 'display_order' => 14, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_status', 'label' => 'Tanggal Status', 'display_order' => 15, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'is_emergency', 'label' => 'Emergency', 'display_order' => 16, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'no_po', 'label' => 'No PO', 'display_order' => 17, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'nama_vendor', 'label' => 'Nama Vendor', 'display_order' => 18, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_po', 'label' => 'Tanggal PO', 'display_order' => 19, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'tgl_datang', 'label' => 'Tanggal Datang', 'display_order' => 20, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'keterangan', 'label' => 'Keterangan', 'display_order' => 21, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_1', 'label' => 'Custom 1', 'display_order' => 22, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_2', 'label' => 'Custom 2', 'display_order' => 23, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_3', 'label' => 'Custom 3', 'display_order' => 24, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_4', 'label' => 'Custom 4', 'display_order' => 25, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
            ['field_name' => 'custom_field_5', 'label' => 'Custom 5', 'display_order' => 26, 'is_visible_in_table' => false, 'is_visible_in_detail' => true],
        ];

        DB::beginTransaction();
        try {
            foreach ($defaultColumns as $columnData) {
                ColumnConfig::where('field_name', $columnData['field_name'])->update(array_merge(
                    $columnData,
                    ['updated_by' => $userId, 'updated_at' => $now]
                ));
            }
            DB::commit();

            return response()->json([
                'message' => 'Konfigurasi kolom berhasil direset ke default',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereset konfigurasi kolom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
