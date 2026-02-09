<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcurementItem;
use App\Models\Status;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics (KPI data)
     */
    public function getMetrics(Request $request)
    {
        $query = ProcurementItem::query();

        // Apply date range filter
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('tgl_terima_dokumen', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('tgl_terima_dokumen', '<=', $request->end_date);
        }

        // Apply department filter
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Apply buyer filter (for personalized buyer dashboard)
        if ($request->has('buyer_id') && $request->buyer_id) {
            $query->where('buyer_id', $request->buyer_id);
        }

        // "Hanya Saya" filter - show ONLY items assigned to current buyer via buyer.user_id
        // (same logic as ProcurementItemController)
        $user = auth()->user();
        if ($request->input('only_mine') === 'true' && $user && $user->role === 'buyer') {
            $query->whereHas('buyer', function ($buyerQuery) use ($user) {
                $buyerQuery->where('user_id', $user->id);
            });
        }

        // Calculate KPI Cards
        $totalPr = $query->count();
        $totalNilai = (clone $query)->sum('nilai');

        // Get status distribution (only active statuses)
        $statuses = Status::where('is_active', true)->orderBy('sort_order')->get();
        $statusDistribution = [];

        foreach ($statuses as $status) {
            $count = (clone $query)->where('status_id', $status->id)->count();
            if ($count > 0) {
                $statusDistribution[] = [
                    'name' => $status->name,
                    'value' => $count,
                    'color' => $status->text_color,
                ];
            }
        }

        // Add items without status
        $noStatusCount = (clone $query)->whereNull('status_id')->count();
        if ($noStatusCount > 0) {
            $statusDistribution[] = [
                'name' => 'Tidak Ada Status',
                'value' => $noStatusCount,
                'color' => '#6b7280',
            ];
        }

        return response()->json([
            'cards' => [
                'total_pr' => $totalPr,
                'total_nilai' => $totalNilai,
            ],
            'status_distribution' => $statusDistribution,
        ]);
    }
}
