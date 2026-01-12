<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Department;
use App\Models\ImportMapping;
use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserImportController extends Controller
{
    /**
     * Excel column header to database field mapping
     */
    private array $columnMapping = [
        'Name' => 'name',
        'Nama' => 'name',
        'Username' => 'username',
        'Password' => 'password',
        'Role' => 'role',
        'Department' => 'department',
        'Bagian' => 'department',
    ];

    /**
     * Required fields for import
     */
    private array $requiredFields = ['name', 'username', 'role'];

    /**
     * Valid roles
     */
    private array $validRoles = ['admin', 'buyer', 'avp', 'staff'];

    /**
     * Upload Excel file and create import session for users
     */
    public function uploadUsers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filename = uniqid() . '_users_' . $originalFilename;
        
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
            
            // Get existing usernames for duplicate detection
            $existingUsernames = User::pluck('username')->map(fn($u) => strtolower($u))->toArray();
            
            $previewData = [];
            $validRows = 0;
            $skippedRows = 0;
            $duplicateRows = 0;
            
            // Collect all usernames from import for duplicate detection within file
            $importUsernames = [];
            
            // Preview first 20 rows
            $previewLimit = min(count($rows), 21); // Header + 20 data rows
            
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
                
                // Validate role
                if (!empty($rowData['role']) && !in_array(strtolower($rowData['role']), $this->validRoles)) {
                    $status = 'error';
                    $errors[] = "Invalid role: {$rowData['role']}. Must be: admin, buyer, avp, or staff";
                }
                
                // Check for duplicate username in database
                if (!empty($rowData['username'])) {
                    $usernameLower = strtolower($rowData['username']);
                    if (in_array($usernameLower, $existingUsernames)) {
                        $status = 'duplicate';
                        $errors[] = "Username already exists in database";
                        $duplicateRows++;
                    } elseif (in_array($usernameLower, $importUsernames)) {
                        $status = 'duplicate';
                        $errors[] = "Duplicate username in import file";
                        $duplicateRows++;
                    } else {
                        $importUsernames[] = $usernameLower;
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
                    'estimated_duplicate' => $duplicateRows,
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
            
            // Cache for lookups
            $departments = Department::pluck('id', 'name')->toArray();
            $existingUsernames = User::pluck('username')->map(fn($u) => strtolower($u))->toArray();
            
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $duplicateCount = 0;
            $errors = [];
            
            // Track usernames added in this import to avoid duplicates within file
            $importedUsernames = [];
            
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
                
                // Validate role
                $role = strtolower(trim($rowData['role']));
                if (!in_array($role, $this->validRoles)) {
                    $errorCount++;
                    $errors[] = "Row $i: Invalid role '{$rowData['role']}'";
                    continue;
                }
                
                // Check for duplicate username
                $usernameLower = strtolower($rowData['username']);
                if (in_array($usernameLower, $existingUsernames) || in_array($usernameLower, $importedUsernames)) {
                    $duplicateCount++;
                    continue;
                }
                
                try {
                    // Resolve department
                    $departmentId = null;
                    if (!empty($rowData['department'])) {
                        $departmentId = $this->resolveDepartment($rowData['department'], $departments);
                    }
                    
                    // Set default password if not provided
                    $password = !empty($rowData['password']) ? $rowData['password'] : 'password123';
                    
                    // Create user
                    $user = User::create([
                        'name' => trim($rowData['name']),
                        'username' => trim($rowData['username']),
                        'password' => Hash::make($password),
                        'role' => $role,
                        'is_active' => true,
                    ]);
                    
                    // Attach department if found
                    if ($departmentId) {
                        $user->departments()->attach($departmentId);
                    }
                    
                    // Create buyer profile if role is buyer
                    if ($role === 'buyer') {
                        $buyerColor = $this->generateBuyerColor();
                        Buyer::create([
                            'name' => trim($rowData['name']),
                            'user_id' => $user->id,
                            'color' => $buyerColor['bg'],
                            'text_color' => $buyerColor['text'],
                            'is_active' => true,
                        ]);
                    }
                    
                    $importedUsernames[] = $usernameLower;
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
                'processed_rows' => $successCount + $duplicateCount + $errorCount + $skippedCount,
                'success_rows' => $successCount,
                'error_rows' => $errorCount,
            ]);
            
            // Clean up file
            Storage::disk('local')->delete('imports/' . $session->filename);
            
            return response()->json([
                'message' => 'Import completed',
                'success_count' => $successCount,
                'duplicate_count' => $duplicateCount,
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
     * Download template file for user import
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Get departments from database
        $departments = Department::where('is_active', true)->pluck('name')->toArray();
        
        // Set headers
        $headers = ['Name', 'Username', 'Password', 'Role', 'Department'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Add example data
        $firstDept = $departments[0] ?? 'IT';
        $secondDept = $departments[1] ?? 'HR';
        $thirdDept = $departments[2] ?? 'Finance';
        
        $exampleData = [
            ['John Doe', 'john.doe', 'password123', 'buyer', $firstDept],
            ['Jane Smith', 'jane.smith', 'password123', 'staff', $secondDept],
            ['Admin User', 'admin.user', 'password123', 'admin', ''],
            ['AVP User', 'avp.user', 'password123', 'avp', $thirdDept],
        ];
        $sheet->fromArray($exampleData, null, 'A2');
        
        // Style header row
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);
        
        // Add data validation for Role column (D2:D1000)
        $roleValidation = $sheet->getCell('D2')->getDataValidation();
        $roleValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $roleValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $roleValidation->setAllowBlank(false);
        $roleValidation->setShowInputMessage(true);
        $roleValidation->setShowErrorMessage(true);
        $roleValidation->setShowDropDown(true);
        $roleValidation->setErrorTitle('Invalid Role');
        $roleValidation->setError('Please select a valid role from the dropdown.');
        $roleValidation->setPromptTitle('Select Role');
        $roleValidation->setPrompt('Choose one: admin, buyer, avp, staff');
        $roleValidation->setFormula1('"admin,buyer,avp,staff"');
        
        // Clone validation to other rows in Role column
        for ($row = 3; $row <= 100; $row++) {
            $sheet->getCell("D{$row}")->setDataValidation(clone $roleValidation);
        }
        
        // Add data validation for Department column (E2:E1000) if departments exist
        if (!empty($departments)) {
            $deptList = implode(',', $departments);
            
            $deptValidation = $sheet->getCell('E2')->getDataValidation();
            $deptValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $deptValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $deptValidation->setAllowBlank(true);
            $deptValidation->setShowInputMessage(true);
            $deptValidation->setShowErrorMessage(true);
            $deptValidation->setShowDropDown(true);
            $deptValidation->setErrorTitle('Invalid Department');
            $deptValidation->setError('Please select a valid department from the dropdown.');
            $deptValidation->setPromptTitle('Select Department');
            $deptValidation->setPrompt('Choose from available departments (optional)');
            $deptValidation->setFormula1('"' . $deptList . '"');
            
            // Clone validation to other rows in Department column
            for ($row = 3; $row <= 100; $row++) {
                $sheet->getCell("E{$row}")->setDataValidation(clone $deptValidation);
            }
        }
        
        // Add notes in a separate section
        $sheet->setCellValue('A8', 'NOTES:');
        $sheet->setCellValue('A9', '- Name, Username, and Role are required fields');
        $sheet->setCellValue('A10', '- Valid roles: admin, buyer, avp, staff (use dropdown)');
        $sheet->setCellValue('A11', '- Password is optional (default: password123)');
        $sheet->setCellValue('A12', '- Department is optional (use dropdown to select)');
        $sheet->setCellValue('A13', '- Duplicate usernames will be skipped');
        
        $sheet->getStyle('A8')->getFont()->setBold(true);
        
        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'user_import_template.xlsx', [
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
            ['value' => 'name', 'label' => 'Name', 'required' => true],
            ['value' => 'username', 'label' => 'Username', 'required' => true],
            ['value' => 'password', 'label' => 'Password', 'required' => false],
            ['value' => 'role', 'label' => 'Role', 'required' => true],
            ['value' => 'department', 'label' => 'Department', 'required' => false],
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
     * Resolve department name to ID
     */
    private function resolveDepartment(string $deptName, array $departments): ?int
    {
        // Exact match
        if (isset($departments[$deptName])) {
            return $departments[$deptName];
        }
        
        // Case-insensitive match
        foreach ($departments as $name => $id) {
            if (strtolower($name) === strtolower($deptName)) {
                return $id;
            }
        }
        
        return null;
    }

    /**
     * Generate a random color for buyer badge
     * Returns an array with 'bg' (background) and 'text' (text color)
     */
    private function generateBuyerColor(): array
    {
        // Predefined harmonious color palette with good contrast
        $colors = [
            ['bg' => '#d4edbc', 'text' => '#2d5016'], // Light green
            ['bg' => '#ffcfc9', 'text' => '#8b2920'], // Light coral
            ['bg' => '#ffc8aa', 'text' => '#7a3d1a'], // Light orange
            ['bg' => '#ffe5a0', 'text' => '#6b5317'], // Light yellow
            ['bg' => '#bfe1f6', 'text' => '#1a4d6b'], // Light blue
            ['bg' => '#e6cff2', 'text' => '#5a2d6b'], // Light purple
            ['bg' => '#c8f7dc', 'text' => '#166534'], // Light mint
            ['bg' => '#fde68a', 'text' => '#78350f'], // Light amber
            ['bg' => '#ddd6fe', 'text' => '#4c1d95'], // Light violet
            ['bg' => '#fbcfe8', 'text' => '#831843'], // Light pink
            ['bg' => '#a7f3d0', 'text' => '#065f46'], // Light emerald
            ['bg' => '#fed7aa', 'text' => '#9a3412'], // Light peach
            ['bg' => '#bfdbfe', 'text' => '#1e40af'], // Light sky blue
            ['bg' => '#fecaca', 'text' => '#991b1b'], // Light red
            ['bg' => '#d9f99d', 'text' => '#3f6212'], // Light lime
            ['bg' => '#e0e7ff', 'text' => '#3730a3'], // Light indigo
        ];

        return $colors[array_rand($colors)];
    }
}
