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
     * List all activity logs with pagination (admin and AVP)
     * AVP can only see logs from procurement items in their assigned departments
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ActivityLog::with(['user', 'procurementItem'])
            ->orderBy('created_at', 'desc');

        // Non-admin users can only see logs from items in their assigned departments
        // BUT also include 'imported' logs (visible to everyone)
        if ($user->role !== 'admin') {
            $departmentIds = $user->departments()->pluck('departments.id')->toArray();
            $query->where(function ($q) use ($departmentIds) {
                $q->whereHas('procurementItem', function ($subQ) use ($departmentIds) {
                    $subQ->whereIn('department_id', $departmentIds);
                })->orWhere('event_type', 'imported');
            });
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                // Search in description
                $q->where('description', 'like', "%{$search}%")
                  // Or search in user name
                  ->orWhereHas('user', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  })
                  // Or search in procurement item PR number
                  ->orWhereHas('procurementItem', function ($subQ) use ($search) {
                      $subQ->where('no_pr', 'like', "%{$search}%");
                  });
            });
        }

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
     * Get activity logs for the logged-in user
     * - Buyer/Staff: logs for items in their departments
     */
    public function myLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ActivityLog::with(['user', 'procurementItem'])
            ->orderBy('created_at', 'desc');

        // Buyer and Staff see logs for all items in their departments
        // BUT also include 'imported' logs (visible to everyone)
        $departmentIds = $user->departments()->pluck('departments.id')->toArray();
        $query->where(function ($q) use ($departmentIds) {
            $q->whereHas('procurementItem', function ($subQ) use ($departmentIds) {
                $subQ->whereIn('department_id', $departmentIds);
            })->orWhere('event_type', 'imported');
        });

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('procurementItem', function ($subQ) use ($search) {
                      $subQ->where('no_pr', 'like', "%{$search}%");
                  });
            });
        }

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

