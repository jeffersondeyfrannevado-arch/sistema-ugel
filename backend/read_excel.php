<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'storage/app/private/documents/KPhj9Q0msZzplXfm6yx1U7xVT8rvvpWbeCEX1xcf.xlsx';

if (!file_exists($filePath)) {
    echo "Error: Excel file does not exist at $filePath\n";
    exit(1);
}

try {
    echo "Loading Excel file: $filePath...\n";
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);
    
    echo "Total rows in Excel (including headers): " . count($rows) . "\n";
    
    // Find the header row (starting with 'DRE')
    $headerRowIdx = -1;
    foreach ($rows as $idx => $row) {
        if (isset($row[0]) && strtoupper(trim((string)$row[0])) === 'DRE') {
            $headerRowIdx = $idx;
            break;
        }
    }
    
    if ($headerRowIdx === -1) {
        echo "Could not find header row starting with 'DRE'. Inspecting first few rows instead:\n";
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            echo "Row $i: " . json_encode(array_slice($rows[$i], 0, 15)) . "\n";
        }
        exit(1);
    }
    
    echo "Header row found at index: $headerRowIdx\n";
    $headers = $rows[$headerRowIdx];
    
    // Map column names to indices
    $cols = [];
    foreach ($headers as $idx => $h) {
        if ($h !== null) {
            $cols[strtoupper(trim($h))] = $idx;
        }
    }
    
    echo "Detected columns:\n";
    print_r($cols);
    
    $tipoIeIdx = $cols['TIPO IE'] ?? $cols['TIPO DE IE'] ?? 11;
    $nombreIeIdx = $cols['NOMBRE DE IE'] ?? $cols['NOMBRE IE'] ?? $cols['INSTITUCION EDUCATIVA'] ?? 8;
    $modalidadIdx = $cols['MODALIDAD'] ?? 10;
    
    $publicCount = 0;
    $privateCount = 0;
    $mypeKeywordsCount = 0;
    $tipoIeValues = [];
    $matchedMypes = [];
    
    $dataStartRow = $headerRowIdx + 2; // Usually starts 2 rows after headers or next row
    for ($i = $dataStartRow; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
            continue;
        }
        
        $tipoIe = trim((string)($row[$tipoIeIdx] ?? ''));
        $nombreIe = trim((string)($row[$nombreIeIdx] ?? ''));
        $modalidad = trim((string)($row[$modalidadIdx] ?? ''));
        
        if ($tipoIe === '' && $nombreIe === '') {
            continue;
        }
        
        if (!isset($tipoIeValues[$tipoIe])) {
            $tipoIeValues[$tipoIe] = 0;
        }
        $tipoIeValues[$tipoIe]++;
        
        // Check if it is public or private based on code or text
        $codigoTipo = substr($tipoIe, 0, 2);
        $esPublico = in_array($codigoTipo, ['A1', 'A2', 'A3', 'A4'], true);
        if ($esPublico) {
            $publicCount++;
        } else {
            $privateCount++;
        }
        
        // Search all cell values in the row for 'mype'
        $rowStr = json_encode($row);
        if (stripos($rowStr, 'mype') !== false) {
            $mypeKeywordsCount++;
            $matchedMypes[] = [
                'row' => $i + 1,
                'ie' => $nombreIe,
                'tipo' => $tipoIe,
                'content' => array_slice($row, 0, 15)
            ];
        }
    }
    
    echo "\n=== STATISTICS ===\n";
    echo "Public institutions count: $publicCount\n";
    echo "Private institutions (potentially MYPES): $privateCount\n";
    echo "Rows matching word 'MYPE' in text: $mypeKeywordsCount\n";
    
    echo "\nTypes of IE distribution:\n";
    print_r($tipoIeValues);
    
    if (count($matchedMypes) > 0) {
        echo "\nMatched MYPE rows details:\n";
        print_r($matchedMypes);
    }
    
} catch (\Exception $e) {
    echo "Error processing Excel file: " . $e->getMessage() . "\n";
}
