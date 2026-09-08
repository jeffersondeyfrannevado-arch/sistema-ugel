<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FiltradoColegioService
{
    /**
     * Procesa un archivo subido en el módulo aislado de filtrado por colegio.
     * Auto-detecta si es NEXUS o REPORTE MATRÍCULA y extrae la lista de colegios.
     */
    private function getWritableDir(string $subfolder): string
    {
        $dir = storage_path("app/{$subfolder}");
        if (@is_dir($dir) || @mkdir($dir, 0755, true)) {
            return $dir;
        }
        $tmpDir = sys_get_temp_dir() . "/{$subfolder}";
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
        return $tmpDir;
    }

    /**
     * Procesa un archivo subido en el módulo aislado de filtrado por colegio.
     * Auto-detecta si es NEXUS o REPORTE MATRÍCULA y extrae la lista de colegios.
     */
    public function procesarArchivoColegios($file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            $name = is_string($file) ? $file : ($file->getClientOriginalName() ?? 'archivo.xlsx');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($ext === 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filePath);
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filePath);
            }
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // Inspeccionar las primeras 10 filas para auto-detección
        $textBuffer = '';
        $maxInspect = min(10, count($rows));
        for ($i = 0; $i < $maxInspect; $i++) {
            if (isset($rows[$i])) {
                $rowStr = implode(' ', array_filter($rows[$i], fn($v) => $v !== null && trim((string)$v) !== ''));
                $textBuffer .= ' ' . strtoupper($rowStr);
            }
        }

        $esNexus = (
            str_contains($textBuffer, 'CODIGO DE PLAZA') ||
            str_contains($textBuffer, 'CUADRO DE PLAZAS NEXUS') ||
            str_contains($textBuffer, 'SITUACION LABORAL') ||
            str_contains($textBuffer, 'NOMBRE DE LA UNIDAD EJECUTORA') ||
            (str_contains($textBuffer, 'CODMOD I.E.') && str_contains($textBuffer, 'MOTIVO DE VACANTE'))
        );

        $tipoExcel = $esNexus ? 'NEXUS' : 'MATRICULA';

        // Extraer colegios según el tipo detectado
        if ($esNexus) {
            $data = $this->extraerColegiosNexus($rows);
        } else {
            $data = $this->extraerColegiosMatricula($rows);
        }

        return [
            'success' => true,
            'tipo_excel' => $tipoExcel,
            'nombre_tipo' => $esNexus ? 'Cuadro de Plazas NEXUS' : 'Reporte de Matrícula (3 Niveles)',
            'total_colegios' => count($data['colegios']),
            'colegios' => $data['colegios'],
            'estadisticas' => $data['estadisticas'],
        ];
    }

    private function extraerColegiosNexus(array $rows): array
    {
        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) $headerRowIdx = 3;

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8;
        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;

        $colegiosMap = [];
        $totalPlazas = 0;
        $nombrados = 0;
        $contratados = 0;
        $vacantes = 0;

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $situacion = strtoupper(trim((string)($row[$situacionColIdx] ?? '')));

            if ($nombreIe === '' && $codMod === '') continue;

            $key = $nombreIe !== '' ? $nombreIe : $codMod;

            if (!isset($colegiosMap[$key])) {
                $colegiosMap[$key] = [
                    'nombre' => $nombreIe,
                    'codigo_ie' => $nombreIe,
                    'codmod' => $codMod,
                    'plazas' => 0,
                ];
            }

            $colegiosMap[$key]['plazas']++;
            $totalPlazas++;

            if (str_contains($situacion, 'NOMBRADO')) $nombrados++;
            elseif (str_contains($situacion, 'CONTRATADO')) $contratados++;
            else $vacantes++;
        }

        return [
            'colegios' => array_values($colegiosMap),
            'estadisticas' => [
                'total_plazas' => $totalPlazas,
                'nombrados' => $nombrados,
                'contratados' => $contratados,
                'vacantes' => $vacantes,
            ]
        ];
    }

    private function extraerColegiosMatricula(array $rows): array
    {
        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'CÓDIGO IE') || str_contains($rowStr, 'CODIGO IE') || str_contains($rowStr, 'CENTRO EDUCATIVO')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) $headerRowIdx = 5;

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['CÓDIGO IE'] ?? $colsMap['CODIGO IE'] ?? 5;
        $nombreColIdx = $colsMap['CENTRO EDUCATIVO'] ?? $colsMap['NOMBRE DE IE'] ?? 6;
        $nivelColIdx = $colsMap['NIVEL EDUCATIVO'] ?? 1;
        $codModColIdx = $colsMap['CÓDIGO MODULAR'] ?? $colsMap['CODIGO MODULAR'] ?? 3;

        $colegiosMap = [];

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $codIe = trim((string)($row[$ieColIdx] ?? ''));
            $nombreIe = trim((string)($row[$nombreColIdx] ?? ''));
            $nivel = trim((string)($row[$nivelColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));

            if ($codIe === '' && $nombreIe === '') continue;

            $key = $codIe !== '' ? $codIe : ($nombreIe !== '' ? $nombreIe : $codMod);

            if (!isset($colegiosMap[$key])) {
                $colegiosMap[$key] = [
                    'codigo_ie' => $codIe,
                    'nombre' => $nombreIe,
                    'codmod' => $codMod,
                    'niveles' => [],
                ];
            }

            if ($nivel !== '' && !in_array($nivel, $colegiosMap[$key]['niveles'], true)) {
                $colegiosMap[$key]['niveles'][] = $nivel;
            }
        }

        return [
            'colegios' => array_values($colegiosMap),
            'estadisticas' => [
                'total_colegios' => count($colegiosMap)
            ]
        ];
    }

    /**
     * Exportación de Excel NEXUS individual (Banner azul en filas 1-2).
     */
    public function exportarNexusColegio($fileSource, string $nombreColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
        $sourceSpreadsheet = IOFactory::load($filePath);
        $sourceSheet = $sourceSpreadsheet->getActiveSheet();
        $rows = $sourceSheet->toArray(null, true, true, false);

        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) $headerRowIdx = 3;

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8;

        $filteredRows = [];
        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

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

        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();
        $outSheet->setTitle('NEXUS - ' . substr($nombreColegio, 0, 15));

        $totalCols = max(66, count($headers));
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        // Banner Azul en Filas 1-2
        $outSheet->mergeCells("A1:{$lastColLetter}1");
        $outSheet->mergeCells("A2:{$lastColLetter}2");

        $outSheet->setCellValue('AL1', 'GOBIERNO REGIONAL DE PIURA - UNIDAD DE GESTIÓN EDUCATIVA LOCAL PIURA');
        $outSheet->setCellValue('AL2', 'CUADRO DE PLAZAS NEXUS - I.E. ' . strtoupper($nombreColegio));

        $bannerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $outSheet->getStyle("A1:{$lastColLetter}2")->applyFromArray($bannerStyle);
        $outSheet->getRowDimension(1)->setRowHeight(22);
        $outSheet->getRowDimension(2)->setRowHeight(22);

        // Fila 3 vacía
        $outSheet->getRowDimension(3)->setRowHeight(12);

        // Cabecera Fila 4
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $outSheet->setCellValue("{$colLetter}4", $headers[$col - 1] ?? '');
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9, 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2439']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334E68']]],
        ];

        $outSheet->getStyle("A4:{$lastColLetter}4")->applyFromArray($headerStyle);
        $outSheet->getRowDimension(4)->setRowHeight(28);

        // Datos Fila 5 en adelante
        $currentRow = 5;
        foreach ($filteredRows as $rData) {
            for ($col = 1; $col <= count($rData); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $outSheet->setCellValue("{$colLetter}{$currentRow}", $rData[$col - 1] ?? '');
            }

            $outSheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
            ]);
            $outSheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        $outputDir = $this->getWritableDir('nexus_output');
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $nombreColegio);
        $filename = "NEXUS - {$safeName}.xlsx";
        $fullPath = "{$outputDir}/{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->save($fullPath);

        return [
            'filename' => $filename,
            'path' => $fullPath,
        ];
    }

    /**
     * Exportación de Excel REPORTE MATRÍCULA individual (5 Secciones coloreadas).
     */
    public function exportarMatriculaColegio($fileSource, string $codigoColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
        $sourceSpreadsheet = IOFactory::load($filePath);
        $sourceSheet = $sourceSpreadsheet->getActiveSheet();
        $rows = $sourceSheet->toArray(null, true, true, false);

        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'CÓDIGO IE') || str_contains($rowStr, 'CODIGO IE') || str_contains($rowStr, 'CENTRO EDUCATIVO')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) $headerRowIdx = 5;

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['CÓDIGO IE'] ?? $colsMap['CODIGO IE'] ?? 5;
        $nombreColIdx = $colsMap['CENTRO EDUCATIVO'] ?? $colsMap['NOMBRE DE IE'] ?? 6;

        $filteredRows = [];
        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $codIe = trim((string)($row[$ieColIdx] ?? ''));
            $nombreIe = trim((string)($row[$nombreColIdx] ?? ''));

            if (
                strcasecmp($codIe, $codigoColegio) === 0 ||
                strcasecmp($nombreIe, $codigoColegio) === 0 ||
                str_contains(strtoupper($codIe), strtoupper($codigoColegio)) ||
                str_contains(strtoupper($nombreIe), strtoupper($codigoColegio))
            ) {
                $filteredRows[] = $row;
            }
        }

        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();
        $outSheet->setTitle('REPORTE - I.E. ' . substr($codigoColegio, 0, 15));

        // Copiar las filas de estructura (0 a $headerRowIdx)
        for ($r = 0; $r <= $headerRowIdx; $r++) {
            $rowValues = $rows[$r] ?? [];
            for ($c = 0; $c < count($rowValues); $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c + 1);
                $outSheet->setCellValue("{$colLetter}" . ($r + 1), $rowValues[$c] ?? '');
            }
        }

        // Aplicar los 5 colores por sección
        $applySectionStyle = function ($range, $bgColor, $textColor = '000000', $bold = true) use ($outSheet) {
            $outSheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => $bold, 'color' => ['rgb' => $textColor], 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0C4DE']]],
            ]);
        };

        $applySectionStyle('A5:O6', 'FFFFFF'); // DETALLE DE IIEE (Blanco)
        $applySectionStyle('F6:F6', 'FFFF00'); // CÓDIGO IE en amarillo brillante
        $applySectionStyle('P5:AP6', 'FFF2CC'); // MATRÍCULA Y GRADOS (Amarillo Claro)
        $applySectionStyle('AR5:AV6', 'FFFF00'); // INFORMACIÓN 2025 (Amarillo)
        $applySectionStyle('AU6:AV6', 'FFFF00'); // COMENTARIO en amarillo brillante
        $applySectionStyle('AW5:BK6', 'D9EAD3'); // PLAZAS Y BOLSA DE HORAS (Verde Claro)
        $applySectionStyle('BL5:BO6', 'F4CCCC'); // FECHA BASES (Rosa Claro)

        // Filas de datos
        $currentOutRow = $headerRowIdx + 2;
        foreach ($filteredRows as $rData) {
            for ($c = 0; $c < count($rData); $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c + 1);
                $outSheet->setCellValue("{$colLetter}{$currentOutRow}", $rData[$c] ?? '');
            }

            $outSheet->getStyle("A{$currentOutRow}:BO{$currentOutRow}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ]);
            $currentOutRow++;
        }

        $outputDir = $this->getWritableDir('matricula_colegios');
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigoColegio);
        $filename = "REPORTE - I.E. {$safeCode}.xlsx";
        $fullPath = "{$outputDir}/{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->save($fullPath);

        return [
            'filename' => $filename,
            'path' => $fullPath,
        ];
    }

    /**
     * Exportación Masiva en archivo ZIP de todos los colegios procesados.
     */
    public function exportarZipColegios($fileSource): array
    {
        $resProceso = $this->procesarArchivoColegios($fileSource);
        $tipoExcel = $resProceso['tipo_excel'];
        $colegios = $resProceso['colegios'] ?? [];

        if (empty($colegios)) {
            throw new Exception('No se encontraron colegios en el archivo para exportar.');
        }

        $outputDir = $this->getWritableDir('temp_zips');
        $zipFileName = $tipoExcel === 'NEXUS' ? 'NEXUS_Todos_Los_Colegios.zip' : 'REPORTE_Todos_Los_Colegios.zip';
        $zipPath = "{$outputDir}/{$zipFileName}";

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo ZIP.');
        }

        $generados = [];
        foreach ($colegios as $c) {
            $key = $c['nombre'] ?? $c['codigo_ie'] ?? $c['codmod'] ?? null;
            if (!$key) continue;

            try {
                if ($tipoExcel === 'NEXUS') {
                    $excelRes = $this->exportarNexusColegio($fileSource, $key);
                } else {
                    $excelRes = $this->exportarMatriculaColegio($fileSource, $key);
                }

                if (file_exists($excelRes['path'])) {
                    $zip->addFile($excelRes['path'], $excelRes['filename']);
                    $generados[] = $excelRes['path'];
                }
            } catch (Exception $e) {
                // Continuar procesando los demás colegios
                continue;
            }
        }

        $zip->close();

        // Limpiar archivos temporales individuales
        foreach ($generados as $path) {
            @unlink($path);
        }
        if (is_dir($tempFolder)) {
            @rmdir($tempFolder);
        }

        return [
            'path' => $zipPath,
            'filename' => $zipFileName,
        ];
    }
}

