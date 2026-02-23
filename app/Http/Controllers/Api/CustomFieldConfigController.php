<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldConfig;
use Illuminate\Http\Request;

class CustomFieldConfigController extends Controller
{
    /**
     * Get all custom field configurations
     */
    public function index()
    {
        $configs = CustomFieldConfig::orderBy('display_order')->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Get only active custom field configurations
     */
    public function getActive()
    {
        $configs = CustomFieldConfig::active()->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Batch update custom field configurations
     */
    public function update(Request $request)
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.id' => 'required|exists:custom_field_configs,id',
            'configs.*.label' => 'nullable|string|max:100',
            'configs.*.is_active' => 'required|boolean',
            'configs.*.is_searchable' => 'required|boolean',
            'configs.*.is_filterable' => 'sometimes|boolean',
        ]);

        $userId = auth()->id();
        $updatedConfigs = [];

        foreach ($request->configs as $configData) {
            $config = CustomFieldConfig::find($configData['id']);
            $updateData = [
                'label' => $configData['label'],
                'is_active' => $configData['is_active'],
                'is_searchable' => $configData['is_searchable'],
                'updated_by' => $userId,
            ];

            // Handle is_filterable
            if (array_key_exists('is_filterable', $configData)) {
                $updateData['is_filterable'] = $configData['is_filterable'];
            }

            // If deactivating, also disable filterable
            if (!$configData['is_active']) {
                $updateData['is_filterable'] = false;
            }

            $config->update($updateData);
            $updatedConfigs[] = $config->fresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi custom fields berhasil diperbarui',
            'data' => $updatedConfigs,
        ]);
    }
}
