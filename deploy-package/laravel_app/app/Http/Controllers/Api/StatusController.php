<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class StatusController extends Controller
{
    /**
     * List all statuses
     */
    public function index(): JsonResponse
    {
        $statuses = Status::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'bg_color', 'text_color', 'sort_order', 'is_active']);

        return response()->json([
            'data' => $statuses,
        ]);
    }

    /**
     * Store a new status
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:statuses,name',
            'bg_color' => 'required|string|max:7',
            'text_color' => 'required|string|max:7',
            'sort_order' => 'integer',
        ]);

        $status = Status::create($validated);

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'created',
            'description' => 'Admin menambahkan master status baru: ' . $status->name,
            'new_values' => $status->toArray(),
        ]);

        return response()->json([
            'message' => 'Status created successfully',
            'data' => $status,
        ], 201);
    }

    /**
     * Update a status
     */
    public function update(Request $request, Status $status): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:statuses,name,' . $status->id,
            'bg_color' => 'required|string|max:7',
            'text_color' => 'required|string|max:7',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $oldValues = $status->only(['name', 'bg_color', 'text_color', 'sort_order', 'is_active']);

        $status->update($validated);

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'edited',
            'description' => 'Admin mengubah master status: ' . $status->name,
            'old_values' => $oldValues,
            'new_values' => $status->refresh()->only(['name', 'bg_color', 'text_color', 'sort_order', 'is_active']),
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $status,
        ]);
    }

    /**
     * Delete a status
     */
    public function destroy(Status $status): JsonResponse
    {
        // Check if status is referenced by procurement items
        $usageCount = \App\Models\ProcurementItem::where('status_id', $status->id)->count();
        if ($usageCount > 0) {
            return response()->json([
                'message' => "Status \"{$status->name}\" tidak dapat dihapus karena masih digunakan oleh {$usageCount} item pengadaan.",
            ], 422);
        }

        $statusName = $status->name;
        $status->delete();

        ActivityLog::create([
            'user_id' => request()->user()?->id ?? null,
            'procurement_item_id' => null,
            'event_type' => 'deleted',
            'description' => 'Admin menghapus master status: ' . $statusName,
        ]);

        return response()->json([
            'message' => 'Status deleted successfully',
        ]);
    }
}
