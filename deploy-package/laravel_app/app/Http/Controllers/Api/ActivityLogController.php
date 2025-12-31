<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\ProcurementItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * List all activity logs with pagination (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with(['user', 'procurementItem'])
            ->orderBy('created_at', 'desc');

        // Filter by event type
        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        // Filter by user
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => ActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get activity logs for the logged-in buyer's items
     */
    public function myLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        // buyer_id now directly references users table, so we can use user->id directly
        $itemIds = ProcurementItem::where('buyer_id', $user->id)->pluck('id')->toArray();

        $query = ActivityLog::with(['user', 'procurementItem'])
            ->whereIn('procurement_item_id', $itemIds)
            ->orderBy('created_at', 'desc');

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => ActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get activity logs for a specific procurement item
     */
    public function forItem(int $itemId): JsonResponse
    {
        $logs = ActivityLog::with(['user'])
            ->where('procurement_item_id', $itemId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ActivityLogResource::collection($logs),
        ]);
    }
}

