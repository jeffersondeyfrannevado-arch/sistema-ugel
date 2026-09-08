<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class NexusService
{
    /**
     * Procesa el archivo NEXUS maestro y extrae estadísticas y lista de colegios.
     */
    public function procesarNexus($file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // Buscar fila de cabecera (debe contener 'NOMBRE DE LA REGION' o 'NOMBRE DE LA INSTITUCION EDUCATIVA')
        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }

        if ($headerRowIdx === -1) {
            $headerRowIdx = 3; // Fila 4 por defecto (índice 3)
        }

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15; // Col P
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8; // Col I
        $cargoColIdx = $colsMap['CARGO'] ?? 22; // Col W
        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23; // Col X

        $colegios = [];
        $totalPlazas = 0;
        $totalNombrados = 0;
        $totalContratados = 0;
        $totalVacantes = 0;

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $situacion = strtoupper(trim((string)($row[$situacionColIdx] ?? '')));

            if ($nombreIe === '' && $codMod === '') {
                continue;
            }

            $keyIe = $nombreIe !== '' ? $nombreIe : $codMod;

            if (!isset($colegios[$keyIe])) {
                $colegios[$keyIe] = [
                    'nombre' => $nombreIe,
                    'codmod' => $codMod,
                    'total_plazas' => 0,
                    'nombrados' => 0,
                    'contratados' => 0,
                    'vacantes' => 0,
                ];
            }

            $colegios[$keyIe]['total_plazas']++;
            $totalPlazas++;

            if (str_contains($situacion, 'NOMBRADO')) {
                $colegios[$keyIe]['nombrados']++;
                $totalNombrados++;
            } elseif (str_contains($situacion, 'CONTRATADO')) {
                $colegios[$keyIe]['contratados']++;
                $totalContratados++;
            } else {
                $colegios[$keyIe]['vacantes']++;
                $totalVacantes++;
            }
        }

        return [
            'tipo' => 'NEXUS',
            'estadisticas' => [
                'total_colegios' => count($colegios),
                'total_plazas' => $totalPlazas,
                'nombrados' => $totalNombrados,
                'contratados' => $totalContratados,
                'vacantes' => $totalVacantes,
            ],
            'colegios' => array_values($colegios),
            'headers' => array_values(array_filter($headers, fn($h) => $h !== null)),
        ];
    }

    /**
     * Genera y exporta el Excel individual de un colegio con el formato exacto del Banner Azul.
     */
    public function exportarColegioNexus($fileSource, string $nombreColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
        $sourceSpreadsheet = IOFactory::load($filePath);
        $sourceSheet = $sourceSpreadsheet->getActiveSheet();
        $rows = $sourceSheet->toArray(null, true, true, false);

        // Buscar fila de cabecera
        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) {
            $headerRowIdx = 3; // Fila 4 (índice 3)
        }

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8;

        // Filtrar filas pertenecientes al colegio
        $filteredRows = [];
        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));

            if (
                strcasecmp($nombreIe, $nombreColegio) === 0 ||
                strcasecmp($codMod, $nombreColegio) === 0 ||
                str_contains(strtoupper($nombreIe), strtoupper($nombreColegio))
            ) {
                $filteredRows[] = $row;
            }
        }

        // Crear nuevo Spreadsheet con el diseño idéntico
        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();
        $outSheet->setTitle('NEXUS - ' . substr($nombreColegio, 0, 20));

        $totalCols = max(66, count($headers));
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        // 1. BANNER AZUL (Filas 1 y 2)
        $outSheet->mergeCells("A1:{$lastColLetter}1");
        $outSheet->mergeCells("A2:{$lastColLetter}2");

        $outSheet->setCellValue('AL1', 'GOBIERNO REGIONAL DE PIURA - UNIDAD DE GESTIÓN EDUCATIVA LOCAL PIURA');
        $outSheet->setCellValue('AL2', 'CUADRO DE PLAZAS NEXUS - I.E. ' . strtoupper($nombreColegio));

        $bannerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1B365D'], // Azul oscuro oficial
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $outSheet->getStyle("A1:{$lastColLetter}2")->applyFromArray($bannerStyle);
        $outSheet->getRowDimension(1)->setRowHeight(22);
        $outSheet->getRowDimension(2)->setRowHeight(22);

        // Fila 3 vacía
        $outSheet->getRowDimension(3)->setRowHeight(12);

        // 2. CABECERA DE TABLA (Fila 4)
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $val = $headers[$col - 1] ?? '';
            $outSheet->setCellValue("{$colLetter}4", $val);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F2439'], // Azul noche para cabecera
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '334E68'],
                ],
            ],
        ];

        $outSheet->getStyle("A4:{$lastColLetter}4")->applyFromArray($headerStyle);
        $outSheet->getRowDimension(4)->setRowHeight(28);

        // 3. DATOS (Fila 5 en adelante)
        $currentRow = 5;
        foreach ($filteredRows as $rData) {
            for ($col = 1; $col <= count($rData); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = $rData[$col - 1] ?? '';
                $outSheet->setCellValue("{$colLetter}{$currentRow}", $val);
            }

            $dataStyle = [
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ];
            $outSheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($dataStyle);
            $outSheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        // Autoajustar ancho de columnas clave
        for ($col = 1; $col <= min($totalCols, 30); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $outSheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Guardar archivo en directorio output
        $outputDir = storage_path('app/nexus_output');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $nombreColegio);
        $filename = "NEXUS - {$safeName}.xlsx";
        $fullPath = "{$outputDir}/{$filename}";

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($outSpreadsheet);
        $writer->save($fullPath);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $fullPath,
            'registros' => count($filteredRows),
        ];
    }
}
