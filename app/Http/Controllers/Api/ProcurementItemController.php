<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcurementItemRequest;
use App\Http\Requests\UpdateProcurementItemRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Http\Resources\ProcurementItemResource;
use App\Models\ActivityLog;
use App\Models\CustomFieldConfig;
use App\Models\FieldPermission;
use App\Models\ProcurementItem;
use App\Models\StatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Search - supports field-specific search via search_field parameter
        if ($search = $request->input('search')) {
            $searchField = $request->input('search_field', 'all');
            
            // Define allowed search fields to prevent SQL injection
            $allowedFields = ['no_pr', 'mat_code', 'nama_barang', 'user_requester'];
            
            // Add searchable custom fields
            $searchableCustomFields = CustomFieldConfig::getSearchableFieldNames();
            $allowedFields = array_merge($allowedFields, $searchableCustomFields);
            
            if ($searchField === 'all' || !in_array($searchField, $allowedFields)) {
                // Search across all fields (default behavior)
                $query->where(function ($q) use ($search, $searchableCustomFields) {
                    $q->where('no_pr', 'like', "%{$search}%")
                      ->orWhere('mat_code', 'like', "%{$search}%")
                      ->orWhere('nama_barang', 'like', "%{$search}%")
                      ->orWhere('user_requester', 'like', "%{$search}%");
                    
                    // Also search in searchable custom fields
                    foreach ($searchableCustomFields as $customField) {
                        $q->orWhere($customField, 'like', "%{$search}%");
                    }
                });
            } else {
                // Search in specific field only
                $query->where($searchField, 'like', "%{$search}%");
            }
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

        // "Hanya Saya"  filter - show ONLY items assigned to current buyer
        // Does NOT include unassigned items
        $user = auth()->user();
        if ($request->input('only_mine') === 'true' && $user && $user->role === 'buyer') {
            // Only items assigned to this buyer (via buyer relationship -> user_id)
            $query->whereHas('buyer', function ($buyerQuery) use ($user) {
                $buyerQuery->where('user_id', $user->id);
            });
        }

        // AVP can only see items from their assigned departments
        if ($user && $user->role === 'avp') {
            $departmentIds = $user->departments()->pluck('departments.id')->toArray();
            if (!empty($departmentIds)) {
                $query->whereIn('department_id', $departmentIds);
            } else {
                // If AVP has no departments assigned, show no items
                $query->whereRaw('1 = 0');
            }
        }


        // Filter by user (requester)
        if ($userRequester = $request->input('user_requester')) {
            $query->where('user_requester', 'like', "%{$userRequester}%");
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
        $this->authorize('create', ProcurementItem::class);
        
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

        // For buyers and staff, automatically update tgl_status when status changes
        if (in_array($user->role, ['buyer', 'staff'])) {
            // Map emergency field for buyer/staff
            if (isset($validated['emergency'])) {
                $validated['is_emergency'] = $validated['emergency'];
                unset($validated['emergency']);
            }
            
            if (isset($validated['status_id']) && $validated['status_id'] != $procurementItem->status_id) {
                $validated['tgl_status'] = now();
            }
        } elseif ($user->role === 'avp') {
            // AVP can only update fields they have permission to edit (from field_permissions table)
            $allowedFields = FieldPermission::getEditableFields('avp');
            $validated = array_intersect_key($validated, array_flip($allowedFields));
            
            // Auto-update tgl_status when status changes
            if (isset($validated['status_id']) && $validated['status_id'] != $procurementItem->status_id) {
                $validated['tgl_status'] = now();
            }
        } else {
            // Admin - Map frontend field names to database column names
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
        
        $oldStatusId = $procurementItem->status_id;
        $oldValues = ['status_id' => $oldStatusId];

        $validated = $request->validated();
        $newStatusId = $validated['status_id'];

        // Use custom date if provided, otherwise use now()
        $changedAt = isset($validated['changed_at']) 
            ? \Carbon\Carbon::parse($validated['changed_at']) 
            : now();

        // Check for duplicate: get the last status history entry for this item
        $lastHistoryEntry = StatusHistory::where('procurement_item_id', $procurementItem->id)
            ->orderBy('changed_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // Prevent duplicate consecutive status entries
        // If the new status is the same as the last history entry's new_status, skip creating a new entry
        $isDuplicate = $lastHistoryEntry && $lastHistoryEntry->new_status_id === $newStatusId;

        // If status is being set, update the date; if cleared, remove the date
        if ($newStatusId !== null) {
            $procurementItem->tgl_status = $changedAt;
        } else {
            $procurementItem->tgl_status = null;
        }
        
        $procurementItem->status_id = $newStatusId;
        $procurementItem->updated_by = $request->user()->id;
        $procurementItem->save();

        // Record status history if status actually changed AND is not a duplicate
        if ($oldStatusId !== $newStatusId && !$isDuplicate) {
            StatusHistory::create([
                'procurement_item_id' => $procurementItem->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'changed_by' => $request->user()->id,
                'changed_at' => $changedAt,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        // Log activity
        $this->logActivity($request, $procurementItem, 'edited', 'Updated status for: ' . $procurementItem->no_pr, $oldValues, ['status_id' => $procurementItem->status_id]);

        return response()->json([
            'message' => $isDuplicate ? 'Status tidak berubah (duplikat)' : 'Status updated successfully',
            'data' => new ProcurementItemResource($procurementItem->load(['department', 'buyer', 'status'])),
            'is_duplicate' => $isDuplicate,
        ]);
    }

    /**
     * Get status history for a procurement item
     */
    public function getStatusHistory(ProcurementItem $procurementItem): JsonResponse
    {
        $this->authorize('view', $procurementItem);

        // Load the creator and buyer for timeline attribution
        $procurementItem->load(['status', 'createdBy', 'buyer', 'buyer.user']);

        $history = StatusHistory::with(['oldStatus', 'newStatus', 'changedByUser'])
            ->where('procurement_item_id', $procurementItem->id)
            ->orderBy('changed_at', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'old_status' => $record->oldStatus ? [
                        'id' => $record->oldStatus->id,
                        'name' => $record->oldStatus->name,
                        'bg_color' => $record->oldStatus->bg_color,
                        'text_color' => $record->oldStatus->text_color,
                    ] : null,
                    'new_status' => $record->newStatus ? [
                        'id' => $record->newStatus->id,
                        'name' => $record->newStatus->name,
                        'bg_color' => $record->newStatus->bg_color,
                        'text_color' => $record->newStatus->text_color,
                    ] : null,
                    'changed_by' => $record->changedByUser ? [
                        'id' => $record->changedByUser->id,
                        'name' => $record->changedByUser->name,
                    ] : null,
                    'changed_at' => $record->changed_at->toIso8601String(),
                    'notes' => $record->notes,
                ];
            });

        return response()->json([
            'data' => $history,
            'item' => [
                'id' => $procurementItem->id,
                'no_pr' => $procurementItem->no_pr,
                'nama_barang' => $procurementItem->nama_barang,
                'tgl_terima_dokumen' => $procurementItem->tgl_terima_dokumen?->toIso8601String(),
                'created_by' => $procurementItem->createdBy ? [
                    'id' => $procurementItem->createdBy->id,
                    'name' => $procurementItem->createdBy->name,
                ] : null,
                'buyer' => $procurementItem->buyer ? [
                    'id' => $procurementItem->buyer->id,
                    'name' => $procurementItem->buyer->name,
                ] : null,
                'current_status' => $procurementItem->status ? [
                    'id' => $procurementItem->status->id,
                    'name' => $procurementItem->status->name,
                    'bg_color' => $procurementItem->status->bg_color,
                    'text_color' => $procurementItem->status->text_color,
                ] : null,
            ],
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
        if (is_array($value)) {
            return json_encode($value);
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
            return $value ?: '-';
        }

        // Format currency
        if ($field === 'nilai') {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    }

    /**
     * Export procurement items to Excel
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ProcurementItem::with(['department', 'buyer', 'status']);

        // Apply same filters as index()
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('no_pr', 'like', "%{$search}%")
                  ->orWhere('nama_barang', 'like', "%{$search}%")
                  ->orWhere('user_requester', 'like', "%{$search}%");
            });
        }

        if ($statusId = $request->input('status_id')) {
            $query->where('status_id', $statusId);
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($buyerId = $request->input('buyer_id')) {
            $query->where('buyer_id', $buyerId);
        }

        if ($user = $request->input('user_requester')) {
            $query->where('user_requester', 'like', "%{$user}%");
        }

        // "Hanya Saya" filter - show ONLY items assigned to current buyer
        // Does NOT include unassigned items
        $currentUser = auth()->user();
        if ($request->input('only_mine') === 'true' && $currentUser && $currentUser->role === 'buyer') {
            // Only items assigned to this buyer (via buyer relationship -> user_id)
            $query->whereHas('buyer', function ($buyerQuery) use ($currentUser) {
                $buyerQuery->where('user_id', $currentUser->id);
            });
        }

        // Get all items (no pagination for export)
        $items = $query->orderBy('created_at', 'desc')->get();

        // Get active custom fields
        $activeCustomFields = CustomFieldConfig::active()->get();

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define headers (21 base columns + active custom fields)
        $headers = [
            'No PR',
            'Mat Code',
            'Nama Barang',
            'Qty',
            'UM',
            'PG',
            'Item Category',
            'User',
            'Nilai',
            'Bagian',
            'Tanggal Terima Dokumen',
            'PROCX/MANUAL',
            'Buyer',
            'Status',
            'Tanggal Status',
            'EMERGENCY',
            'NO PO',
            'Nama Vendor',
            'Tanggal PO',
            'Tanggal Datang',
            'Keterangan',
        ];

        // Add active custom field headers
        foreach ($activeCustomFields as $config) {
            $headers[] = $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name));
        }

        // Write headers
        $sheet->fromArray($headers, null, 'A1');

        // Calculate last column letter
        $lastColIndex = count($headers);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);

        // Write data rows
        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item->no_pr);
            $sheet->setCellValue('B' . $row, $item->mat_code);
            $sheet->setCellValue('C' . $row, $item->nama_barang);
            $sheet->setCellValue('D' . $row, $item->qty);
            $sheet->setCellValue('E' . $row, $item->um);
            $sheet->setCellValue('F' . $row, $item->pg);
            $sheet->setCellValue('G' . $row, $item->item_category);
            $sheet->setCellValue('H' . $row, $item->user_requester);
            $sheet->setCellValue('I' . $row, $item->nilai);
            $sheet->setCellValue('J' . $row, $item->department?->name);
            $sheet->setCellValue('K' . $row, $item->tgl_terima_dokumen?->format('d/m/Y'));
            $sheet->setCellValue('L' . $row, $item->procx_manual);
            $sheet->setCellValue('M' . $row, $item->buyer?->name);
            $sheet->setCellValue('N' . $row, $item->status?->name);
            $sheet->setCellValue('O' . $row, $item->tgl_status?->format('d/m/Y'));
            $sheet->setCellValue('P' . $row, $item->is_emergency ?: '-');
            $sheet->setCellValue('Q' . $row, $item->no_po);
            $sheet->setCellValue('R' . $row, $item->nama_vendor);
            $sheet->setCellValue('S' . $row, $item->tgl_po?->format('d/m/Y'));
            $sheet->setCellValue('T' . $row, $item->tgl_datang?->format('d/m/Y'));
            $sheet->setCellValue('U' . $row, $item->keterangan);
            
            // Add custom field values
            $colIndex = 22; // V is column 22
            foreach ($activeCustomFields as $config) {
                $fieldName = $config->field_name;
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter . $row, $item->$fieldName);
                $colIndex++;
            }
            
            $row++;
        }

        // Format Nilai column as number
        $sheet->getStyle('I2:I' . ($row - 1))->getNumberFormat()
            ->setFormatCode('#,##0');

        // Auto-size columns
        for ($i = 1; $i <= $lastColIndex; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Generate filename with timestamp
        $filename = 'procurement_export_' . date('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
