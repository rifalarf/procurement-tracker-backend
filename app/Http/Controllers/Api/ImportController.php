<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Department;
use App\Models\ImportMapping;
use App\Models\ImportSession;
use App\Models\ProcurementItem;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class ImportController extends Controller
{
    /**
     * Excel column header to database field mapping
     */
    private array $columnMapping = [
        'NO PR' => 'no_pr',
        'Mat Code' => 'mat_code',
        'Nama Barang' => 'nama_barang',
        'Qty' => 'qty',
        'UM' => 'um',
        'PG' => 'pg',
        'User' => 'user_requester',
        'Nilai' => 'nilai',
        'Bagian' => 'department_id',
        'Tanggal Terima Dokumen' => 'tgl_terima_dokumen',
        'PROCX/MANUAL' => 'procx_manual',
        'Buyer' => 'buyer_id',
        'Status' => 'status_id',
        'Tanggal Status' => 'tgl_status',
        'EMERGENCY' => 'is_emergency',
        'NO PO' => 'no_po',
        'Nama Vendor' => 'nama_vendor',
        'Tanggal PO' => 'tgl_po',
        'Tanggal Datang' => 'tgl_datang',
        'Keterangan' => 'keterangan',
    ];

    /**
     * Required fields for import
     */
    private array $requiredFields = ['no_pr'];

    /**
     * Upload Excel file and create import session
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filename = uniqid() . '_' . $originalFilename;
        
        // Store file temporarily
        $path = $file->storeAs('imports', $filename, 'local');
        
        try {
            // Read Excel file
            $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                Storage::disk('local')->delete($path);
                return response()->json(['message' => 'File is empty'], 422);
            }
            
            // Get headers from first row
            $headers = array_map('trim', $rows[0]);
            $totalRows = count($rows) - 1; // Exclude header row
            
            // Create import session
            $session = ImportSession::create([
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_size' => $file->getSize(),
                'total_rows' => $totalRows,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);
            
            // Create column mappings with auto-detection
            foreach ($headers as $index => $header) {
                if (empty($header)) continue;
                
                $databaseField = $this->autoDetectField($header);
                $sampleData = $this->getSampleData($rows, $index);
                $confidence = $databaseField ? $this->calculateConfidence($header, $databaseField) : 0;
                
                ImportMapping::create([
                    'import_session_id' => $session->id,
                    'excel_column' => $header,
                    'database_field' => $databaseField,
                    'sample_data' => $sampleData,
                    'confidence_score' => $confidence,
                ]);
            }
            
            return response()->json([
                'message' => 'File uploaded successfully',
                'session_id' => $session->id,
                'total_rows' => $totalRows,
                'columns_detected' => count(array_filter($headers)),
            ]);
            
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => 'Failed to parse Excel file: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Get import session with mappings
     */
    public function getSession(int $id): JsonResponse
    {
        $session = ImportSession::with('mappings')->findOrFail($id);
        
        return response()->json([
            'session' => $session,
            'available_fields' => $this->getAvailableFields(),
        ]);
    }

    /**
     * Update column mappings
     */
    public function updateMapping(Request $request, int $id): JsonResponse
    {
        $session = ImportSession::findOrFail($id);
        
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.id' => 'required|exists:import_mappings,id',
            'mappings.*.database_field' => 'nullable|string',
        ]);
        
        foreach ($request->mappings as $mapping) {
            ImportMapping::where('id', $mapping['id'])
                ->where('import_session_id', $session->id)
                ->update(['database_field' => $mapping['database_field']]);
        }
        
        return response()->json(['message' => 'Mappings updated successfully']);
    }

    /**
     * Preview import data
     */
    public function preview(int $id): JsonResponse
    {
        $session = ImportSession::with('mappings')->findOrFail($id);
        
        $filePath = Storage::disk('local')->path('imports/' . $session->filename);
        
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $headers = array_map('trim', $rows[0]);
            $mappings = $session->mappings->keyBy('excel_column');
            
            // Get existing NO PRs to check duplicates
            $existingNoPrs = ProcurementItem::pluck('no_pr')->toArray();
            
            $previewData = [];
            $validRows = 0;
            $skippedRows = 0;
            $duplicateRows = 0;
            
            // Preview first 10 rows
            $previewLimit = min(count($rows), 11); // Header + 10 data rows
            
            for ($i = 1; $i < $previewLimit; $i++) {
                $row = $rows[$i];
                $rowData = $this->mapRowToFields($row, $headers, $mappings);
                
                $status = 'valid';
                $errors = [];
                
                // Check required fields
                foreach ($this->requiredFields as $field) {
                    if (empty($rowData[$field])) {
                        $status = 'skip';
                        $errors[] = "Missing required field: $field";
                    }
                }
                
                // Check duplicates
                if (!empty($rowData['no_pr']) && in_array($rowData['no_pr'], $existingNoPrs)) {
                    $status = 'duplicate';
                    $errors[] = "NO PR already exists";
                    $duplicateRows++;
                }
                
                if ($status === 'valid') {
                    $validRows++;
                } elseif ($status === 'skip') {
                    $skippedRows++;
                }
                
                $previewData[] = [
                    'row_number' => $i,
                    'data' => $rowData,
                    'status' => $status,
                    'errors' => $errors,
                ];
            }
            
            return response()->json([
                'preview' => $previewData,
                'summary' => [
                    'total_rows' => $session->total_rows,
                    'preview_count' => count($previewData),
                    'estimated_valid' => $validRows,
                    'estimated_skipped' => $skippedRows,
                    'estimated_duplicates' => $duplicateRows,
                ],
                'mappings' => $session->mappings,
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to preview: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Execute import
     */
    public function execute(int $id): JsonResponse
    {
        $session = ImportSession::with('mappings')->findOrFail($id);
        
        if ($session->status !== 'pending') {
            return response()->json(['message' => 'Import already processed'], 422);
        }
        
        $filePath = Storage::disk('local')->path('imports/' . $session->filename);
        
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        try {
            $session->update(['status' => 'processing']);
            
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $headers = array_map('trim', $rows[0]);
            $mappings = $session->mappings->keyBy('excel_column');
            
            // Get existing NO PRs to check duplicates
            $existingNoPrs = ProcurementItem::pluck('no_pr')->toArray();
            
            // Cache for lookups
            $departments = Department::pluck('id', 'name')->toArray();
            $buyers = \App\Models\User::where('role', 'buyer')->pluck('id', 'name')->toArray();
            $statuses = Status::pluck('id', 'name')->toArray();
            
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $errors = [];
            
            DB::beginTransaction();
            
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowData = $this->mapRowToFields($row, $headers, $mappings);
                
                // Skip if missing required fields
                $missingRequired = false;
                foreach ($this->requiredFields as $field) {
                    if (empty($rowData[$field])) {
                        $missingRequired = true;
                        break;
                    }
                }
                
                if ($missingRequired) {
                    $skippedCount++;
                    continue;
                }
                
                // Skip duplicates
                if (in_array($rowData['no_pr'], $existingNoPrs)) {
                    $skippedCount++;
                    continue;
                }
                
                try {
                    // Resolve lookups
                    $rowData = $this->resolveLookups($rowData, $departments, $buyers, $statuses);
                    
                    // Parse dates
                    $rowData = $this->parseDates($rowData);
                    
                    // Parse special fields
                    $rowData = $this->parseSpecialFields($rowData);
                    
                    // Add metadata
                    $rowData['created_by'] = auth()->id();
                    
                    ProcurementItem::create($rowData);
                    $existingNoPrs[] = $rowData['no_pr']; // Add to duplicate check
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Row $i: " . $e->getMessage();
                }
            }
            
            DB::commit();
            
            // Update session
            $session->update([
                'status' => 'completed',
                'processed_rows' => $successCount + $errorCount + $skippedCount,
                'success_rows' => $successCount,
                'error_rows' => $errorCount,
            ]);
            
            // Clean up file
            Storage::disk('local')->delete('imports/' . $session->filename);
            
            return response()->json([
                'message' => 'Import completed',
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'skipped_count' => $skippedCount,
                'errors' => array_slice($errors, 0, 10), // Return first 10 errors
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            $session->update(['status' => 'failed']);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download template file
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = array_keys($this->columnMapping);
        $sheet->fromArray($headers, null, 'A1');
        
        // Add example data
        $exampleData = [
            'PR-001',           // NO PR
            'MAT-001',          // Mat Code
            'Contoh Barang',    // Nama Barang
            '10',               // Qty
            'PCS',              // UM
            'PG-001',           // PG
            'John Doe',         // User
            '1000000',          // Nilai
            'IT',               // Bagian
            '2024-01-15',       // Tanggal Terima Dokumen
            'PROCX',            // PROCX/MANUAL
            'Buyer Name',       // Buyer
            'Pending',          // Status
            '2024-01-20',       // Tanggal Status
            'No',               // EMERGENCY
            'PO-001',           // NO PO
            'PT Vendor',        // Nama Vendor
            '2024-01-25',       // Tanggal PO
            '2024-02-01',       // Tanggal Datang
            'Keterangan contoh', // Keterangan
        ];
        $sheet->fromArray($exampleData, null, 'A2');
        
        // Style header row
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);
        
        // Auto-size columns
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Auto-detect database field from Excel header
     */
    private function autoDetectField(string $header): ?string
    {
        $header = trim($header);
        
        // Exact match
        if (isset($this->columnMapping[$header])) {
            return $this->columnMapping[$header];
        }
        
        // Case-insensitive match
        foreach ($this->columnMapping as $excelHeader => $dbField) {
            if (strtolower($header) === strtolower($excelHeader)) {
                return $dbField;
            }
        }
        
        // Partial match
        $headerLower = strtolower($header);
        foreach ($this->columnMapping as $excelHeader => $dbField) {
            if (str_contains($headerLower, strtolower($excelHeader)) ||
                str_contains(strtolower($excelHeader), $headerLower)) {
                return $dbField;
            }
        }
        
        return null;
    }

    /**
     * Get sample data from rows
     */
    private function getSampleData(array $rows, int $columnIndex): ?string
    {
        for ($i = 1; $i < min(count($rows), 4); $i++) {
            if (!empty($rows[$i][$columnIndex])) {
                return (string) $rows[$i][$columnIndex];
            }
        }
        return null;
    }

    /**
     * Calculate confidence score for auto-mapping
     */
    private function calculateConfidence(string $header, string $dbField): int
    {
        $header = strtolower(trim($header));
        
        foreach ($this->columnMapping as $excelHeader => $field) {
            if ($field === $dbField) {
                if (strtolower($excelHeader) === $header) {
                    return 100;
                }
                if (str_contains($header, strtolower($excelHeader))) {
                    return 80;
                }
                return 60;
            }
        }
        
        return 50;
    }

    /**
     * Get available database fields for mapping dropdown
     */
    private function getAvailableFields(): array
    {
        return [
            ['value' => 'no_pr', 'label' => 'NO PR', 'required' => true],
            ['value' => 'mat_code', 'label' => 'Mat Code', 'required' => false],
            ['value' => 'nama_barang', 'label' => 'Nama Barang', 'required' => false],
            ['value' => 'qty', 'label' => 'Qty', 'required' => false],
            ['value' => 'um', 'label' => 'UM', 'required' => false],
            ['value' => 'pg', 'label' => 'PG', 'required' => false],
            ['value' => 'user_requester', 'label' => 'User', 'required' => false],
            ['value' => 'nilai', 'label' => 'Nilai', 'required' => false],
            ['value' => 'department_id', 'label' => 'Bagian', 'required' => false],
            ['value' => 'tgl_terima_dokumen', 'label' => 'Tanggal Terima Dokumen', 'required' => false],
            ['value' => 'procx_manual', 'label' => 'PROCX/MANUAL', 'required' => false],
            ['value' => 'buyer_id', 'label' => 'Buyer', 'required' => false],
            ['value' => 'status_id', 'label' => 'Status', 'required' => false],
            ['value' => 'tgl_status', 'label' => 'Tanggal Status', 'required' => false],
            ['value' => 'is_emergency', 'label' => 'EMERGENCY', 'required' => false],
            ['value' => 'no_po', 'label' => 'NO PO', 'required' => false],
            ['value' => 'nama_vendor', 'label' => 'Nama Vendor', 'required' => false],
            ['value' => 'tgl_po', 'label' => 'Tanggal PO', 'required' => false],
            ['value' => 'tgl_datang', 'label' => 'Tanggal Datang', 'required' => false],
            ['value' => 'keterangan', 'label' => 'Keterangan', 'required' => false],
        ];
    }

    /**
     * Map row data to database fields
     */
    private function mapRowToFields(array $row, array $headers, $mappings): array
    {
        $data = [];
        
        foreach ($headers as $index => $header) {
            if (empty($header)) continue;
            
            $mapping = $mappings->get($header);
            if ($mapping && $mapping->database_field) {
                $value = $row[$index] ?? null;
                $data[$mapping->database_field] = is_string($value) ? trim($value) : $value;
            }
        }
        
        return $data;
    }

    /**
     * Resolve lookup fields (Department, Buyer, Status)
     */
    private function resolveLookups(array $data, array $departments, array $buyers, array $statuses): array
    {
        // Resolve department
        if (isset($data['department_id']) && !is_numeric($data['department_id'])) {
            $deptName = $data['department_id'];
            $data['department_id'] = $departments[$deptName] ?? null;
            
            // Try case-insensitive match
            if (!$data['department_id']) {
                foreach ($departments as $name => $id) {
                    if (strtolower($name) === strtolower($deptName)) {
                        $data['department_id'] = $id;
                        break;
                    }
                }
            }
        }
        
        // Resolve buyer
        if (isset($data['buyer_id']) && !is_numeric($data['buyer_id'])) {
            $buyerName = $data['buyer_id'];
            $data['buyer_id'] = $buyers[$buyerName] ?? null;
            
            if (!$data['buyer_id']) {
                foreach ($buyers as $name => $id) {
                    if (strtolower($name) === strtolower($buyerName)) {
                        $data['buyer_id'] = $id;
                        break;
                    }
                }
            }
        }
        
        // Resolve status
        if (isset($data['status_id']) && !is_numeric($data['status_id'])) {
            $statusName = $data['status_id'];
            $data['status_id'] = $statuses[$statusName] ?? null;
            
            if (!$data['status_id']) {
                foreach ($statuses as $name => $id) {
                    if (strtolower($name) === strtolower($statusName)) {
                        $data['status_id'] = $id;
                        break;
                    }
                }
            }
        }
        
        return $data;
    }

    /**
     * Parse date fields with auto-detection
     */
    private function parseDates(array $data): array
    {
        $dateFields = ['tgl_terima_dokumen', 'tgl_status', 'tgl_po', 'tgl_datang'];
        
        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->parseDate($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Parse single date with auto-detection
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        // If it's a numeric value (Excel serial date)
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        
        // Try parsing as string
        try {
            $date = Carbon::parse($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse special fields (boolean, numeric)
     */
    private function parseSpecialFields(array $data): array
    {
        // Parse is_emergency - default to false if not set or empty
        if (isset($data['is_emergency']) && !empty($data['is_emergency'])) {
            $value = strtolower(trim((string) $data['is_emergency']));
            $data['is_emergency'] = in_array($value, ['yes', 'ya', 'true', '1', 'y']);
        } else {
            $data['is_emergency'] = false;
        }
        
        // Parse qty - default to 0 if not set
        if (isset($data['qty']) && $data['qty'] !== null && $data['qty'] !== '') {
            $data['qty'] = (int) $data['qty'];
        } else {
            $data['qty'] = 0;
        }
        
        // Parse nilai - default to 0 if not set
        if (isset($data['nilai']) && $data['nilai'] !== null && $data['nilai'] !== '') {
            $nilai = str_replace([',', '.'], ['', '.'], (string) $data['nilai']);
            $data['nilai'] = (float) preg_replace('/[^0-9.]/', '', $nilai);
        } else {
            $data['nilai'] = 0;
        }
        
        return $data;
    }
}
