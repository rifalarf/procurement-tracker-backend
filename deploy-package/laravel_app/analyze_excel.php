<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require '/home/rifal/pupuk-kujang/pengadaan-tracker/backend/vendor/autoload.php';

$inputFileName = '/home/rifal/pupuk-kujang/pengadaan-tracker/backend/fix_data.xlsx';

echo "Reading file: $inputFileName\n";

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // Asumsi header ada di baris pertama
    $headers = array_map('trim', $rows[0]);
    $noPrIndex = null;
    $namaBarangIndex = null;

    // Cari index kolom
    foreach ($headers as $index => $header) {
        if (strcasecmp($header, 'NO PR') === 0) {
            $noPrIndex = $index;
        }
        if (strcasecmp($header, 'Nama Barang') === 0) {
            $namaBarangIndex = $index;
        }
    }

    if ($noPrIndex === null) {
        die("Error: Kolom 'NO PR' tidak ditemukan.\n");
    }

    echo "Total Baris (termasuk header): " . count($rows) . "\n";
    echo "Total Data (exclude header): " . (count($rows) - 1) . "\n\n";

    $uniqueKeys = [];
    $duplicates = [];
    $emptyRows = 0;

    // Mulai dari baris ke-2 (index 1)
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $noPr = isset($row[$noPrIndex]) ? trim($row[$noPrIndex]) : '';
        $namaBarang = ($namaBarangIndex !== null && isset($row[$namaBarangIndex])) ? trim($row[$namaBarangIndex]) : '';

        // Skip hidden empty rows at the end
        if (empty($noPr)) {
            $emptyRows++;
            continue;
        }

        // Key unik: NO PR + Nama Barang
        $key = $noPr . '|' . $namaBarang;

        if (isset($uniqueKeys[$key])) {
            $duplicates[] = [
                'row' => $i + 1, // Baris Excel (1-indexed)
                'original_row' => $uniqueKeys[$key],
                'no_pr' => $noPr,
                'nama_barang' => $namaBarang
            ];
        } else {
            $uniqueKeys[$key] = $i + 1;
        }
    }

    $totalData = count($rows) - 1;
    $validData = $totalData - $emptyRows;
    $uniqueCount = count($uniqueKeys);
    $duplicateCount = count($duplicates);

    echo "=== HASIL ANALISIS ===\n";
    echo "Total Baris Data Valid (Non-Kosong): $validData\n";
    echo "Total Data Unik (NO PR + Nama Barang): $uniqueCount\n";
    echo "Total Duplikat Ditemukan: $duplicateCount\n";
    echo "Total Baris Kosong (Skipped): $emptyRows\n";

    echo "\n=== DETAIL DUPLIKAT (10 Pertama) ===\n";
    $count = 0;
    foreach ($duplicates as $dupe) {
        echo "- Baris {$dupe['row']} duplikat dari Baris {$dupe['original_row']}: [{$dupe['no_pr']}] {$dupe['nama_barang']}\n";
        $count++;
        if ($count >= 10)
            break;
    }
    if ($duplicateCount > 10) {
        echo "... dan " . ($duplicateCount - 10) . " lainnya.\n";
    }

} catch (Exception $e) {
    echo 'Error loading file: ', $e->getMessage(), "\n";
}
