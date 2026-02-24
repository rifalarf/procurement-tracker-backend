<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Buyer;
use App\Models\CustomFieldConfig;
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
        'No PR' => 'no_pr',
        'B/J' => null,
        'Acc' => null,
        'Mat Code' => 'mat_code',
        'Nama Barang' => 'nama_barang',
        'Qty' => 'qty',
        'UM' => 'um',
        'PG' => 'pg',
        'Item Category' => 'item_category',
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
     * Alias mapping for common typos and legacy templates
     * These will not be included in the template download
     */
    private array $aliasMapping = [
        'Item Categori' => 'item_category',
        'Kategori Item' => 'item_category',
        'Kategori Barang' => 'item_category',
        'Jenis Barang' => 'item_category',
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
            $rows = $this->worksheetToRawArray($worksheet);

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
                if (empty($header))
                    continue;

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
            $rows = $this->worksheetToRawArray($worksheet);

            $headers = array_map('trim', $rows[0]);
            $mappings = $session->mappings->keyBy('excel_column');

            $previewData = [];
            $validRows = 0;
            $skippedRows = 0;

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
            $rows = $this->worksheetToRawArray($worksheet);

            $headers = array_map('trim', $rows[0]);
            $mappings = $session->mappings->keyBy('excel_column');

            // Cache for lookups
            $departments = Department::pluck('id', 'name')->toArray();
            $buyers = Buyer::pluck('id', 'name')->toArray();
            $statuses = Status::pluck('id', 'name')->toArray();

            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $updatedCount = 0;
            $errors = [];
            $importLog = [];

            DB::beginTransaction();

            $dataRows = [];
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

                $dataRows[] = [
                    'original_index' => $i,
                    'row_data' => $rowData
                ];
            }

            // Sort dataRows by tgl_terima_dokumen
            usort($dataRows, function ($a, $b) {
                $dateA = $this->parseDate($a['row_data']['tgl_terima_dokumen'] ?? null);
                $dateB = $this->parseDate($b['row_data']['tgl_terima_dokumen'] ?? null);

                if (empty($dateA) && empty($dateB))
                    return 0;
                if (empty($dateA))
                    return 1; // Put empty dates at the end
                if (empty($dateB))
                    return -1;

                return strtotime($dateA) <=> strtotime($dateB);
            });

            foreach ($dataRows as $dataRow) {
                $i = $dataRow['original_index'];
                $rowData = $dataRow['row_data'];

                try {
                    // Resolve lookups
                    $rowData = $this->resolveLookups($rowData, $departments, $buyers, $statuses);

                    // Parse dates
                    $rowData = $this->parseDates($rowData);

                    // Parse special fields
                    $rowData = $this->parseSpecialFields($rowData);

                    // Add metadata
                    $rowData['created_by'] = auth()->id();

                    $noPr = $rowData['no_pr'];
                    $namaBarang = $rowData['nama_barang'] ?? null;

                    // Check if item with same NO PR + nama_barang exists
                    // This is the unique key combination
                    $existingItem = ProcurementItem::where('no_pr', $noPr)
                        ->where('nama_barang', $namaBarang)
                        ->first();

                    if ($existingItem) {
                        // Same NO PR + same item → UPDATE existing row
                        // Remove created_by from update data (keep original creator)
                        unset($rowData['created_by']);
                        $rowData['updated_by'] = auth()->id();

                        // Keep original no_pr and version
                        unset($rowData['no_pr']);
                        unset($rowData['version']);

                        $existingItem->update($rowData);
                        $updatedCount++;
                        $importLog[] = [
                            'row' => $i,
                            'action' => 'UPDATE',
                            'no_pr' => $noPr,
                            'nama_barang' => substr($namaBarang ?? '', 0, 30),
                            'id' => $existingItem->id,
                            'version' => $existingItem->version,
                            'reason' => 'Updated existing item (same NO PR + nama_barang)',
                        ];
                    } else {
                        // NO PR exists but different item, OR first occurrence
                        // → INSERT new row

                        // Count existing items with same NO PR to determine version/sequence
                        // Version 1 = first item (no badge), Version 2+ = badge number
                        $existingCount = ProcurementItem::where('no_pr', $noPr)->count();
                        $rowData['version'] = $existingCount + 1;

                        $successCount++;
                        $newItem = ProcurementItem::create($rowData);
                        $importLog[] = [
                            'row' => $i,
                            'action' => 'NEW',
                            'no_pr' => $noPr,
                            'nama_barang' => substr($namaBarang ?? '', 0, 30),
                            'version' => $rowData['version'],
                            'new_id' => $newItem->id,
                        ];
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Row $i: " . $e->getMessage();
                    $importLog[] = [
                        'row' => $i,
                        'action' => 'ERROR',
                        'no_pr' => $rowData['no_pr'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            // Update session
            $session->update([
                'status' => 'completed',
                'processed_rows' => $successCount + $updatedCount + $errorCount + $skippedCount,
                'success_rows' => $successCount + $updatedCount,
                'error_rows' => $errorCount,
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'procurement_item_id' => null,
                'event_type' => 'imported',
                'description' => "Imported procurement data from '{$session->original_filename}': {$successCount} new, {$updatedCount} updated, {$skippedCount} skipped, {$errorCount} errors",
                'old_values' => null,
                'new_values' => [
                    'filename' => $session->original_filename,
                    'success_count' => $successCount,
                    'updated_count' => $updatedCount,
                    'skipped_count' => $skippedCount,
                    'error_count' => $errorCount,
                    'total_rows' => $session->total_rows,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Clean up file
            Storage::disk('local')->delete('imports/' . $session->filename);

            // Log to file for debugging
            $logContent = "=== IMPORT LOG ===" . PHP_EOL;
            $logContent .= "Session ID: {$session->id}" . PHP_EOL;
            $logContent .= "Timestamp: " . now()->format('Y-m-d H:i:s') . PHP_EOL;
            $logContent .= "Results: New={$successCount}, Updated={$updatedCount}, Skipped={$skippedCount}, Errors={$errorCount}" . PHP_EOL;
            $logContent .= PHP_EOL . "=== DETAIL ===" . PHP_EOL;
            foreach ($importLog as $log) {
                $logContent .= "[Row {$log['row']}] {$log['action']}";
                $logContent .= " | No PR: {$log['no_pr']}";
                if (isset($log['nama_barang']))
                    $logContent .= " | Item: {$log['nama_barang']}";
                if (isset($log['version']))
                    $logContent .= " | Version: {$log['version']}";
                if (isset($log['new_id']))
                    $logContent .= " | ID: {$log['new_id']}";
                if (isset($log['reason']))
                    $logContent .= " | Reason: {$log['reason']}";
                if (isset($log['error']))
                    $logContent .= " | Error: {$log['error']}";
                $logContent .= PHP_EOL;
            }

            Storage::disk('local')->put('import_logs/import_' . $session->id . '.log', $logContent);

            return response()->json([
                'message' => 'Import completed',
                'success_count' => $successCount,
                'updated_count' => $updatedCount,
                'error_count' => $errorCount,
                'skipped_count' => $skippedCount,
                'errors' => array_slice($errors, 0, 10), // Return first 10 errors
                'log' => $importLog, // Include log in response
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

        // Set headers (standard + custom fields)
        $headers = array_keys($this->columnMapping);

        // Add active custom field headers
        $activeCustomFields = CustomFieldConfig::active()->get();
        foreach ($activeCustomFields as $config) {
            $label = $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name));
            if (!in_array($label, array_keys($this->columnMapping))) {
                $headers[] = $label;
            }
        }

        $sheet->fromArray($headers, null, 'A1');

        // Add example data
        $exampleData = [
            'PR-001',           // No PR
            '',                 // B/J
            'K',                // Acc
            'MAT-001',          // Mat Code
            'Contoh Barang',    // Nama Barang
            '10',               // Qty
            'PCS',              // UM
            'PG-001',           // PG
            'Spare Part',       // Item Category
            'John Doe',         // User
            '1000000',          // Nilai
            'IT',               // Bagian
            '2024-01-15',       // Tanggal Terima Dokumen
            'PROCX',            // PROCX/MANUAL
            'Buyer Name',       // Buyer
            'Negosiasi',          // Status
            '2024-01-20',       // Tanggal Status
            'No',               // EMERGENCY
            'PO-001',           // NO PO
            'PT Vendor',        // Nama Vendor
            '2024-01-25',       // Tanggal PO
            '2024-02-01',       // Tanggal Datang
            'Keterangan contoh', // Keterangan
        ];

        // Add empty example values for custom fields
        foreach ($activeCustomFields as $config) {
            $label = $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name));
            if (!in_array($label, array_keys($this->columnMapping))) {
                $exampleData[] = '';
            }
        }

        $sheet->fromArray($exampleData, null, 'A2');

        // Style header row - dynamic range
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);

        // Auto-size columns
        for ($i = 1; $i <= count($headers); $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
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
        if (array_key_exists($header, $this->columnMapping) && $this->columnMapping[$header] !== null) {
            return $this->columnMapping[$header];
        }

        // Case-insensitive match
        foreach ($this->columnMapping as $excelHeader => $dbField) {
            if ($dbField !== null && strtolower($header) === strtolower($excelHeader)) {
                return $dbField;
            }
        }

        // Partial match
        $headerLower = strtolower($header);
        foreach ($this->columnMapping as $excelHeader => $dbField) {
            if (
                $dbField !== null && (
                    str_contains($headerLower, strtolower($excelHeader)) ||
                    str_contains(strtolower($excelHeader), $headerLower)
                )
            ) {
                return $dbField;
            }
        }

        // Dynamic custom field matching
        $activeCustomFields = CustomFieldConfig::active()->get();
        foreach ($activeCustomFields as $config) {
            $label = $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name));
            if (strtolower($header) === strtolower($label)) {
                return $config->field_name;
            }
            // Partial match for custom fields
            if (
                str_contains($headerLower, strtolower($label)) ||
                str_contains(strtolower($label), $headerLower)
            ) {
                return $config->field_name;
            }
        }

        // Alias match
        if (array_key_exists($header, $this->aliasMapping)) {
            return $this->aliasMapping[$header];
        }

        foreach ($this->aliasMapping as $excelHeader => $dbField) {
            if (strtolower($header) === strtolower($excelHeader)) {
                return $dbField;
            }
        }

        foreach ($this->aliasMapping as $excelHeader => $dbField) {
            if (
                str_contains($headerLower, strtolower($excelHeader)) ||
                str_contains(strtolower($excelHeader), $headerLower)
            ) {
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

        // Check against main column mappings
        foreach ($this->columnMapping as $excelHeader => $field) {
            if ($field !== null && $field === $dbField) {
                if (strtolower($excelHeader) === $header) {
                    return 100;
                }
                if (str_contains($header, strtolower($excelHeader))) {
                    return 80;
                }
                return 60;
            }
        }

        // Check against aliases
        foreach ($this->aliasMapping as $excelHeader => $field) {
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

        // Check against custom fields
        $activeCustomFields = CustomFieldConfig::active()->get();
        foreach ($activeCustomFields as $config) {
            if ($config->field_name === $dbField) {
                $label = $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name));
                $labelLower = strtolower($label);

                if ($labelLower === $header) {
                    return 100;
                }
                if (str_contains($header, $labelLower) || str_contains($labelLower, $header)) {
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
        $fields = [
            ['value' => 'no_pr', 'label' => 'No PR', 'required' => true],
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
            ['value' => 'item_category', 'label' => 'Item Category', 'required' => false],
        ];

        // Add active custom fields dynamically
        $activeCustomFields = CustomFieldConfig::active()->get();
        foreach ($activeCustomFields as $config) {
            $fields[] = [
                'value' => $config->field_name,
                'label' => $config->label ?? ucfirst(str_replace('_', ' ', $config->field_name)),
                'required' => false,
            ];
        }

        return $fields;
    }

    /**
     * Map row data to database fields
     */
    private function mapRowToFields(array $row, array $headers, $mappings): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            if (empty($header))
                continue;

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
        if (isset($data['department_id']) && $data['department_id'] !== null && $data['department_id'] !== '' && !is_numeric($data['department_id'])) {
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
        } elseif (!isset($data['department_id']) || $data['department_id'] === '' || $data['department_id'] === null) {
            // Empty or null department_id - set to null explicitly
            $data['department_id'] = null;
        }

        // Resolve buyer
        if (isset($data['buyer_id']) && $data['buyer_id'] !== null && $data['buyer_id'] !== '') {
            $buyerInput = $data['buyer_id'];

            if (is_numeric($buyerInput)) {
                // Numeric buyer_id - validate it exists in buyers table
                $buyerId = (int) $buyerInput;
                if (in_array($buyerId, $buyers)) {
                    $data['buyer_id'] = $buyerId;
                } else {
                    $data['buyer_id'] = null;
                }
            } else {
                // String buyer name - normalize then resolve
                $normalizedName = $this->normalizeBuyerName($buyerInput);

                // Try to find exact ID match for normalized name
                $foundId = null;

                // 1. Direct lookup from cache (exact match)
                if (isset($buyers[$normalizedName])) {
                    $foundId = $buyers[$normalizedName];
                }

                // 2. Case-insensitive lookup
                if (!$foundId) {
                    foreach ($buyers as $name => $id) {
                        if (strtolower($name) === strtolower($normalizedName)) {
                            $foundId = $id;
                            break;
                        }
                    }
                }

                $data['buyer_id'] = $foundId;
            }
        } else {
            // Empty or null buyer_id - set to null explicitly
            $data['buyer_id'] = null;
        }

        // Resolve status
        if (isset($data['status_id']) && $data['status_id'] !== null && $data['status_id'] !== '' && !is_numeric($data['status_id'])) {
            $statusName = $data['status_id'];

            // Apply legacy status alias mapping from config
            $legacyMapping = config('procurement_flow.legacy_status_mapping', []);
            if (isset($legacyMapping[$statusName])) {
                $statusName = $legacyMapping[$statusName];
            }

            $data['status_id'] = $statuses[$statusName] ?? null;

            if (!$data['status_id']) {
                foreach ($statuses as $name => $id) {
                    if (strtolower($name) === strtolower($statusName)) {
                        $data['status_id'] = $id;
                        break;
                    }
                }
            }
        } elseif (!isset($data['status_id']) || $data['status_id'] === '' || $data['status_id'] === null) {
            // Empty or null status_id - set to null explicitly
            $data['status_id'] = null;
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
            // If the field exists in the data array (mapped column)
            if (array_key_exists($field, $data)) {
                if (empty($data[$field])) {
                    // Explicitly set to null if empty string or null
                    $data[$field] = null;
                } else {
                    // Parse if value exists
                    $data[$field] = $this->parseDate($data[$field]);
                }
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

        $value = trim((string) $value);

        // Explicitly check for DD/MM/YYYY or DD-MM-YYYY format
        // This prevents Carbon from incorrectly interpreting it as MM/DD/YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            if (checkdate((int) $month, (int) $day, (int) $year)) {
                return "{$year}-{$month}-{$day}";
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
        // Parse is_emergency - now stores as string, not boolean
        if (isset($data['is_emergency']) && !empty($data['is_emergency'])) {
            $data['is_emergency'] = trim((string) $data['is_emergency']);
        } else {
            $data['is_emergency'] = null;
        }

        // Parse qty - default to 0 if not set
        if (isset($data['qty']) && $data['qty'] !== null && $data['qty'] !== '') {
            $data['qty'] = (int) $data['qty'];
        } else {
            $data['qty'] = 0;
        }

        // Parse nilai - default to 0 if not set
        if (isset($data['nilai']) && $data['nilai'] !== null && $data['nilai'] !== '') {
            $nilai = (string) $data['nilai'];

            // Check if it's already a plain number (from Excel numeric cell)
            if (is_numeric($data['nilai'])) {
                $data['nilai'] = (float) $data['nilai'];
            } else {
                // Handle Indonesian format: dots as thousand separators, comma as decimal
                // e.g., "568.453.820" or "1.234.567,89"

                // Count dots and commas to determine format
                $dotCount = substr_count($nilai, '.');
                $commaCount = substr_count($nilai, ',');

                if ($dotCount > 1 || ($dotCount >= 1 && $commaCount == 0)) {
                    // Indonesian format: dots are thousand separators
                    // Remove all dots (thousand separators)
                    $nilai = str_replace('.', '', $nilai);
                    // Replace comma with dot if present (decimal separator)
                    $nilai = str_replace(',', '.', $nilai);
                } else {
                    // US/International format: commas are thousand separators, dot is decimal
                    $nilai = str_replace(',', '', $nilai);
                }

                // Remove any remaining non-numeric characters except dot
                $data['nilai'] = (float) preg_replace('/[^0-9.]/', '', $nilai);
            }
        } else {
            $data['nilai'] = 0;
        }

        // Parse custom_field_1 (B/J - Barang/Jasa)
        if (isset($data['custom_field_1'])) {
            $bjValue = trim(strtoupper((string) $data['custom_field_1']));

            if ($bjValue === 'D') {
                $data['custom_field_1'] = 'Jasa';
            } elseif ($bjValue === '' || $bjValue === null) {
                // Fallback to checking Mat Code
                $hasMatCode = isset($data['mat_code']) && trim((string) $data['mat_code']) !== '';
                $data['custom_field_1'] = $hasMatCode ? 'Barang' : 'Jasa';
            }
        } else {
            // Extrapolate B/J if custom_field_1 key doesn't even exist in the data yet
            $hasMatCode = isset($data['mat_code']) && trim((string) $data['mat_code']) !== '';
            $data['custom_field_1'] = $hasMatCode ? 'Barang' : 'Jasa';
        }

        return $data;
    }

    /**
     * Read worksheet to array with raw values (not formatted)
     * This prevents Excel time format from corrupting No PR values
     */
    private function worksheetToRawArray($worksheet): array
    {
        $highestRow = $worksheet->getHighestRow();
        $highestColumnLetter = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumnLetter);

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cell = $worksheet->getCell($colLetter . $row);
                $value = $cell->getValue();

                // Convert numeric values to string, preserving decimals
                // This handles cases where Excel formats the number as time
                if (is_numeric($value)) {
                    if (floor($value) == $value) {
                        // Integer value - safe to truncate decimals (e.g., No PR)
                        $value = (string) intval($value);
                    } else {
                        // Decimal value - preserve decimals (e.g., nilai, qty)
                        $value = (string) $value;
                    }
                }

                $rowData[] = $value;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Normalize buyer name based on common aliases/variations
     * Source: fix_data.xlsx
     */
    private function normalizeBuyerName(string $name): string
    {
        $name = trim($name);
        if (empty($name)) {
            return $name;
        }

        // Canonical names from fix_data.xlsx
        $mapping = [
            'akbar' => 'Akbar Faturahman',
            'ato' => 'Ato Heryanto',
            'cholida' => 'Cholida Maranani',
            'dian' => 'Dian Sholihat',
            'dicky' => 'Dicky Setiagraha',
            'eggy' => 'Eggy Baharudin',
            'erik' => 'Erik Erdiana',
            'erwin' => 'Erwin Herdiana',
            'gugun' => 'Gugun GT',
            'heru' => 'Heru Winata Praja',
            'mutia' => 'Mutia Virgiana',
            'nawang' => 'Nawang Wulan',
            'tathu' => 'Tathu RA',
        ];

        $lowerName = strtolower($name);

        foreach ($mapping as $key => $target) {
            // Check for exact match of alias or if the alias is contained in the name
            if (str_contains($lowerName, $key)) {
                return $target;
            }
        }

        return $name;
    }
}
