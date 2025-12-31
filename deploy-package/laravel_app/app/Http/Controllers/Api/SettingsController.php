<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProcurementItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Purge all procurement data (danger zone)
     */
    public function purgeData(Request $request): JsonResponse
    {
        $request->validate([
            'confirm' => 'required|string|in:DELETE ALL DATA',
        ]);

        // Log the action before purging
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event_type' => 'deleted',
            'description' => 'Purged all procurement data',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Delete all procurement items (this will cascade to activity logs via foreign key)
        ProcurementItem::query()->forceDelete();

        // Delete activity logs related to procurement items
        ActivityLog::whereNotNull('procurement_item_id')->delete();

        return response()->json([
            'message' => 'All procurement data has been deleted',
        ]);
    }
}
