<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldConfig;
use App\Models\FieldPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class FieldPermissionController extends Controller
{
    /**
     * All available procurement item fields
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
        // Custom fields are added dynamically
    ];

    /**
     * Custom fields (added dynamically based on active configs)
     */
    private const CUSTOM_FIELDS = [
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
    ];

    /**
     * Human-readable labels for fields
     */
    private const FIELD_LABELS = [
        'no_pr' => 'No PR',
        'mat_code' => 'Mat Code',
        'nama_barang' => 'Nama Barang',
        'item_category' => 'Item Category',
        'qty' => 'Qty',
        'um' => 'UM',
        'pg' => 'PG',
        'user_requester' => 'User (Requester)',
        'nilai' => 'Nilai',
        'department_id' => 'Bagian',
        'tgl_terima_dokumen' => 'Tgl Terima Dokumen',
        'procx_manual' => 'PROCX/MANUAL',
        'buyer_id' => 'Buyer',
        'status_id' => 'Status',
        'tgl_status' => 'Tanggal Status',
        'is_emergency' => 'Emergency',
        'no_po' => 'No PO',
        'nama_vendor' => 'Nama Vendor',
        'tgl_po' => 'Tanggal PO',
        'tgl_datang' => 'Tanggal Datang',
        'keterangan' => 'Keterangan',
        // Custom field labels are fetched dynamically
    ];

    /**
     * Get all fields including active custom fields
     */
    private function getAllFieldsWithCustom(): array
    {
        $fields = self::ALL_FIELDS;
        
        // Add all custom fields (not just active ones - permissions should exist for all)
        foreach (self::CUSTOM_FIELDS as $customField) {
            $fields[] = $customField;
        }
        
        return $fields;
    }

    /**
     * Get field labels including custom field labels from config
     */
    private function getFieldLabels(): array
    {
        $labels = self::FIELD_LABELS;
        
        // Get custom field labels from configs
        $customConfigs = CustomFieldConfig::all()->keyBy('field_name');
        foreach (self::CUSTOM_FIELDS as $customField) {
            $config = $customConfigs->get($customField);
            // Use the configured label, or fallback to default name
            $labels[$customField] = $config && $config->label 
                ? $config->label
                : ucfirst(str_replace('_', ' ', $customField));
        }
        
        return $labels;
    }

    /**
     * Get all permissions grouped by role
     */
    public function index(): JsonResponse
    {
        $permissions = FieldPermission::all();
        $fieldLabels = $this->getFieldLabels();
        $allFields = $this->getAllFieldsWithCustom();
        
        // Group by role
        $grouped = [
            'admin' => [],
            'staff' => [],
            'avp' => [],
            'buyer' => [],
        ];

        foreach ($permissions as $permission) {
            $role = $permission->role;
            if (isset($grouped[$role])) {
                $grouped[$role][] = [
                    'id' => $permission->id,
                    'role' => $permission->role,
                    'field_name' => $permission->field_name,
                    'field_label' => $fieldLabels[$permission->field_name] ?? $permission->field_name,
                    'can_view' => $permission->can_view,
                    'can_edit' => $permission->can_edit,
                    'is_custom_field' => in_array($permission->field_name, self::CUSTOM_FIELDS),
                ];
            }
        }

        // Sort each role's permissions by field order
        foreach ($grouped as $role => &$perms) {
            usort($perms, function ($a, $b) use ($allFields) {
                $orderA = array_search($a['field_name'], $allFields);
                $orderB = array_search($b['field_name'], $allFields);
                if ($orderA === false) $orderA = 999;
                if ($orderB === false) $orderB = 999;
                return $orderA - $orderB;
            });
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * Update a single permission
     */
    public function update(Request $request, FieldPermission $permission): JsonResponse
    {
        $validated = $request->validate([
            'can_view' => 'required|boolean',
            'can_edit' => 'required|boolean',
        ]);

        // If can_edit is true, can_view must also be true
        if ($validated['can_edit'] && !$validated['can_view']) {
            return response()->json([
                'message' => 'Cannot edit a field that is not viewable',
                'errors' => ['can_edit' => ['Can edit requires can view to be enabled']]
            ], 422);
        }

        $permission->update($validated);
        
        // Clear cache for this role
        FieldPermission::clearCache($permission->role);

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => [
                'id' => $permission->id,
                'role' => $permission->role,
                'field_name' => $permission->field_name,
                'field_label' => self::FIELD_LABELS[$permission->field_name] ?? $permission->field_name,
                'can_view' => $permission->can_view,
                'can_edit' => $permission->can_edit,
            ]
        ]);
    }

    /**
     * Bulk update multiple permissions
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.id' => 'required|integer|exists:field_permissions,id',
            'permissions.*.can_view' => 'required|boolean',
            'permissions.*.can_edit' => 'required|boolean',
        ]);

        $updatedCount = 0;
        $errors = [];
        $rolesToClear = [];

        foreach ($validated['permissions'] as $permData) {
            // Validate can_edit requires can_view
            if ($permData['can_edit'] && !$permData['can_view']) {
                $errors[] = "Permission ID {$permData['id']}: Cannot edit a field that is not viewable";
                continue;
            }

            $permission = FieldPermission::find($permData['id']);
            if ($permission) {
                $permission->update([
                    'can_view' => $permData['can_view'],
                    'can_edit' => $permData['can_edit'],
                ]);
                $rolesToClear[$permission->role] = true;
                $updatedCount++;
            }
        }

        // Clear cache for affected roles
        foreach (array_keys($rolesToClear) as $role) {
            FieldPermission::clearCache($role);
        }

        $response = [
            'message' => "Updated {$updatedCount} permissions",
            'updated_count' => $updatedCount,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response);
    }

    /**
     * Reset all permissions to defaults
     */
    public function reset(): JsonResponse
    {
        try {
            // Run the seeder
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\PermissionSeeder',
                '--force' => true,
            ]);

            return response()->json([
                'message' => 'Permissions reset to defaults successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all available field names with labels
     */
    public function fields(): JsonResponse
    {
        $allFields = $this->getAllFieldsWithCustom();
        $fieldLabels = $this->getFieldLabels();
        
        $fields = [];
        foreach ($allFields as $field) {
            $fields[] = [
                'name' => $field,
                'label' => $fieldLabels[$field] ?? $field,
                'is_custom_field' => in_array($field, self::CUSTOM_FIELDS),
            ];
        }

        return response()->json(['data' => $fields]);
    }
}
