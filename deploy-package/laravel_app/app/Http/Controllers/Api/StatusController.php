<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $status->update($validated);

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

        $status->delete();

        return response()->json([
            'message' => 'Status deleted successfully',
        ]);
    }
}
