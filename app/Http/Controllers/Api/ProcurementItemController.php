<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcurementItemRequest;
use App\Http\Requests\UpdateProcurementItemRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Http\Resources\ProcurementItemResource;
use App\Models\ActivityLog;
use App\Models\ProcurementItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementItemController extends Controller
{
    /**
     * Allowed columns for sorting to prevent SQL injection
     */
    private const ALLOWED_SORT_COLUMNS = [
        'id',
        'no_pr',
        'nama_barang',
        'qty',
        'nilai',
        'created_at',
        'updated_at',
        'tgl_terima_dokumen',
        'tgl_status',
        'tgl_po',
        'tgl_datang',
        'status_id',
        'department_id',
        'buyer_id',
        'user_requester',
        'item_category',
    ];

    /**
     * List all procurement items with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProcurementItem::with(['department', 'buyer', 'status']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('no_pr', 'like', "%{$search}%")
                  ->orWhere('nama_barang', 'like', "%{$search}%")
                  ->orWhere('user_requester', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($statusId = $request->input('status_id')) {
            $query->where('status_id', $statusId);
        }

        // Filter by department
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Filter by buyer (buyer_id now references users table directly)
        if ($buyerId = $request->input('buyer_id')) {
            $query->where('buyer_id', $buyerId);
        }

        // Filter for buyer visibility: show items assigned to this user OR unassigned items
        // Used by MemberDashboard to show items the buyer can claim
        // We need to join with buyers table to check buyer.user_id matches the requesting user
        if ($forBuyerUserId = $request->input('for_buyer')) {
            $query->where(function ($q) use ($forBuyerUserId) {
                $q->whereHas('buyer', function ($buyerQuery) use ($forBuyerUserId) {
                    $buyerQuery->where('user_id', $forBuyerUserId);
                })->orWhereNull('buyer_id');
            });
        }

        // Filter by user (requester)
        if ($user = $request->input('user_requester')) {
            $query->where('user_requester', 'like', "%{$user}%");
        }

        // Sorting - validate against whitelist to prevent SQL injection
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        
        if (!in_array($sortBy, self::ALLOWED_SORT_COLUMNS)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }
        
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => ProcurementItemResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single procurement item
     */
    public function show(ProcurementItem $procurementItem): JsonResponse
    {
        $this->authorize('view', $procurementItem);
        
        $procurementItem->load(['department', 'buyer', 'status']);

        return response()->json([
            'data' => new ProcurementItemResource($procurementItem),
        ]);
    }

    /**
     * Get unique user_requester values for filtering
     */
    public function getUserRequesters(): JsonResponse
    {
        $users = ProcurementItem::whereNotNull('user_requester')
            ->where('user_requester', '!=', '')
            ->distinct()
            ->orderBy('user_requester')
            ->pluck('user_requester');

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Store a new procurement item
     */
    public function store(StoreProcurementItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Map frontend field names to database column names
        if (isset($validated['emergency'])) {
            $validated['is_emergency'] = $validated['emergency'];
            unset($validated['emergency']);
        }

        $validated['created_by'] = $request->user()->id;

        $item = ProcurementItem::create($validated);

        // Log activity
        $this->logActivity($request, $item, 'created', 'Created procurement item: ' . $item->no_pr);

        return response()->json([
            'message' => 'Procurement item created successfully',
            'data' => new ProcurementItemResource($item->load(['department', 'buyer', 'status'])),
        ], 201);
    }

    /**
     * Update a procurement item
     */
    public function update(UpdateProcurementItemRequest $request, ProcurementItem $procurementItem): JsonResponse
    {
        $this->authorize('update', $procurementItem);
        
        $oldValues = $procurementItem->toArray();
        $user = $request->user();
        $validated = $request->validated();

        // For buyers, automatically update tgl_status when status changes
        if ($user->role === 'buyer') {
            if (isset($validated['status_id']) && $validated['status_id'] != $procurementItem->status_id) {
                $validated['tgl_status'] = now();
            }
        } else {
            // Map frontend field names to database column names
            if (isset($validated['emergency'])) {
                $validated['is_emergency'] = $validated['emergency'];
                unset($validated['emergency']);
            }
        }

        $validated['updated_by'] = $user->id;

        $procurementItem->update($validated);

        // Log activity
        $this->logActivity($request, $procurementItem, 'edited', 'Updated procurement item: ' . $procurementItem->no_pr, $oldValues, $procurementItem->toArray());

        return response()->json([
            'message' => 'Procurement item updated successfully',
            'data' => new ProcurementItemResource($procurementItem->load(['department', 'buyer', 'status'])),
        ]);
    }

    /**
     * Update only the status of a procurement item
     */
    public function updateStatus(UpdateStatusRequest $request, ProcurementItem $procurementItem): JsonResponse
    {
        $this->authorize('update', $procurementItem);
        
        $oldValues = ['status_id' => $procurementItem->status_id];

        $validated = $request->validated();

        // If status is being set, update the date; if cleared, remove the date
        if ($validated['status_id'] !== null) {
            $validated['tgl_status'] = now();
        } else {
            $validated['tgl_status'] = null;
        }
        
        $validated['updated_by'] = $request->user()->id;

        $procurementItem->update($validated);

        // Log activity
        $this->logActivity($request, $procurementItem, 'edited', 'Updated status for: ' . $procurementItem->no_pr, $oldValues, ['status_id' => $procurementItem->status_id]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => new ProcurementItemResource($procurementItem->load(['department', 'buyer', 'status'])),
        ]);
    }

    /**
     * Update only the buyer of a procurement item
     */
    public function updateBuyer(UpdateBuyerRequest $request, ProcurementItem $procurementItem): JsonResponse
    {
        $this->authorize('assignBuyer', $procurementItem);
        
        $oldValues = ['buyer_id' => $procurementItem->buyer_id];

        $validated = $request->validated();

        $validated['updated_by'] = $request->user()->id;

        $procurementItem->update($validated);

        // Log activity
        $this->logActivity($request, $procurementItem, 'edited', 'Updated buyer for: ' . $procurementItem->no_pr, $oldValues, ['buyer_id' => $procurementItem->buyer_id]);

        return response()->json([
            'message' => 'Buyer updated successfully',
            'data' => new ProcurementItemResource($procurementItem->load(['department', 'buyer', 'status'])),
        ]);
    }

    /**
     * Soft delete a procurement item
     */
    public function destroy(Request $request, ProcurementItem $procurementItem): JsonResponse
    {
        // Log activity before deletion
        $this->logActivity($request, $procurementItem, 'deleted', 'Deleted procurement item: ' . $procurementItem->no_pr);

        $procurementItem->delete();

        return response()->json([
            'message' => 'Procurement item deleted successfully',
        ]);
    }

    /**
     * Log activity for procurement item changes
     */
    private function logActivity(Request $request, ProcurementItem $item, string $eventType, string $description, ?array $oldValues = null, ?array $newValues = null): void
    {
        // Generate detailed description for edits
        $detailedDescription = $description;
        
        if ($eventType === 'edited' && $oldValues && $newValues) {
            $changes = $this->getDetailedChanges($oldValues, $newValues);
            if (!empty($changes)) {
                $detailedDescription = implode('; ', $changes);
            }
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'procurement_item_id' => $item->id,
            'event_type' => $eventType,
            'description' => $detailedDescription,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Get detailed changes between old and new values
     */
    private function getDetailedChanges(array $oldValues, array $newValues): array
    {
        $changes = [];
        
        // Field labels for display
        $fieldLabels = [
            'no_pr' => 'No PR',
            'mat_code' => 'Mat Code',
            'nama_barang' => 'Nama Barang',
            'item_category' => 'Item Category',
            'qty' => 'Qty',
            'um' => 'UM',
            'pg' => 'PG',
            'user_requester' => 'User',
            'nilai' => 'Nilai',
            'department_id' => 'Bagian',
            'tgl_terima_dokumen' => 'Tgl Terima Dokumen',
            'procx_manual' => 'PROCX/MANUAL',
            'buyer_id' => 'Buyer',
            'status_id' => 'Status',
            'tgl_status' => 'Tgl Status',
            'is_emergency' => 'Emergency',
            'no_po' => 'No PO',
            'nama_vendor' => 'Nama Vendor',
            'tgl_po' => 'Tgl PO',
            'tgl_datang' => 'Tgl Datang',
            'keterangan' => 'Keterangan',
        ];

        // Fields to skip in comparison
        $skipFields = ['updated_at', 'updated_by', 'created_at', 'created_by', 'id'];

        foreach ($newValues as $key => $newValue) {
            if (in_array($key, $skipFields)) {
                continue;
            }

            $oldValue = $oldValues[$key] ?? null;
            
            // Normalize values for comparison
            $normalizedOld = $this->normalizeValue($oldValue);
            $normalizedNew = $this->normalizeValue($newValue);

            if ($normalizedOld !== $normalizedNew) {
                $label = $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                $displayOld = $this->formatValueForDisplay($key, $oldValue);
                $displayNew = $this->formatValueForDisplay($key, $newValue);
                
                $changes[] = "{$label} diubah dari \"{$displayOld}\" ke \"{$displayNew}\"";
            }
        }

        return $changes;
    }

    /**
     * Normalize value for comparison
     */
    private function normalizeValue($value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }

    /**
     * Format value for display in activity log
     */
    private function formatValueForDisplay(string $field, $value): string
    {
        if (is_null($value) || $value === '') {
            return '-';
        }

        // Handle foreign key fields - get the related model's name
        if ($field === 'status_id') {
            $status = \App\Models\Status::find($value);
            return $status ? $status->name : (string) $value;
        }

        if ($field === 'department_id') {
            $department = \App\Models\Department::find($value);
            return $department ? $department->name : (string) $value;
        }

        if ($field === 'buyer_id') {
            $buyer = \App\Models\User::find($value);
            return $buyer ? $buyer->name : (string) $value;
        }

        if ($field === 'is_emergency') {
            return $value ? 'Ya' : 'Tidak';
        }

        // Format currency
        if ($field === 'nilai') {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    }
}

