<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class DepartmentController extends Controller
{
    /**
     * List all departments
     */
    public function index(): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active']);

        return response()->json([
            'data' => $departments,
        ]);
    }

    /**
     * Store a new department
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:departments,name',
            'description' => 'nullable|string|max:255',
        ]);

        $department = Department::create($validated);

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'created',
            'description' => 'Admin menambahkan departemen baru: ' . $department->name,
            'new_values' => $department->toArray(),
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    /**
     * Update a department
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:departments,name,' . $department->id,
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $oldValues = $department->only(['name', 'description', 'is_active']);

        $department->update($validated);

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'edited',
            'description' => 'Admin mengubah data departemen: ' . $department->name,
            'old_values' => $oldValues,
            'new_values' => $department->refresh()->only(['name', 'description', 'is_active']),
        ]);

        return response()->json([
            'message' => 'Department updated successfully',
            'data' => $department,
        ]);
    }

    /**
     * Delete a department
     */
    public function destroy(Department $department): JsonResponse
    {
        // Check if department is referenced by procurement items
        $usageCount = \App\Models\ProcurementItem::where('department_id', $department->id)->count();
        if ($usageCount > 0) {
            return response()->json([
                'message' => "Department \"{$department->name}\" tidak dapat dihapus karena masih digunakan oleh {$usageCount} item pengadaan.",
            ], 422);
        }

        $departmentName = $department->name;
        $department->delete();

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'deleted',
            'description' => 'Admin menghapus departemen: ' . $departmentName,
        ]);

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
