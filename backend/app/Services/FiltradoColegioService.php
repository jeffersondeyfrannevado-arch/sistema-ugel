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
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $spreadsheet = IOFactory::load($filePath);
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
        $colsMap = [];
        $headerRowIdx = 3;

        for ($r = 0; $r < min(10, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            foreach ($row as $cIdx => $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $cleanVal = strtoupper(trim((string)$val));
                    if (!isset($colsMap[$cleanVal])) {
                        $colsMap[$cleanVal] = $cIdx;
                    }
                }
            }
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA') || str_contains($rowStr, 'CODMOD I.E.')) {
                $headerRowIdx = $r;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? $colsMap['INSTITUCION EDUCATIVA'] ?? 15;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? $colsMap['CODIGO MODULAR'] ?? 8;
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
        $colsMap = [];
        $headerRowIdx = 5;

        for ($r = 0; $r < min(12, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            foreach ($row as $cIdx => $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $cleanVal = strtoupper(trim((string)$val));
                    if (!isset($colsMap[$cleanVal])) {
                        $colsMap[$cleanVal] = $cIdx;
                    }
                }
            }
            $rowStr = strtoupper(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'CENTRO EDUCATIVO') || str_contains($rowStr, 'CÓDIGO IE') || str_contains($rowStr, 'CODIGO IE')) {
                $headerRowIdx = $r;
            }
        }

        $ieColIdx = $colsMap['CÓDIGO IE'] ?? $colsMap['CODIGO IE'] ?? 4;
        $nombreColIdx = $colsMap['CENTRO EDUCATIVO'] ?? $colsMap['NOMBRE DE IE'] ?? 5;
        $nivelColIdx = $colsMap['NIVEL EDUCATIVO'] ?? 1;
        $codModColIdx = $colsMap['CÓDIGO MODULAR'] ?? $colsMap['CODIGO MODULAR'] ?? 3;
        $codLocalColIdx = $colsMap['CÓDIGO LOCAL'] ?? $colsMap['CODIGO LOCAL'] ?? 2;

        $colegiosMap = [];

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));
            $codIe = trim((string)($row[$ieColIdx] ?? ''));
            $nombreIe = trim((string)($row[$nombreColIdx] ?? ''));
            $nivel = trim((string)($row[$nivelColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));

            if ($codIe === '' && $nombreIe === '' && $codLocal === '') continue;

            $key = $codIe !== '' ? $codIe : ($nombreIe !== '' ? $nombreIe : ($codLocal !== '' ? $codLocal : $codMod));

            if (!isset($colegiosMap[$key])) {
                $colegiosMap[$key] = [
                    'codigo_local' => $codLocal,
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
        try {
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            $sourceSpreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $sourceSpreadsheet = IOFactory::load($filePath);
        }
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

        $outSheet->setCellValue('A1', 'GOBIERNO REGIONAL DE PIURA - UNIDAD DE GESTIÓN EDUCATIVA LOCAL PIURA');
        $outSheet->setCellValue('A2', 'CUADRO DE PLAZAS NEXUS - I.E. ' . strtoupper($nombreColegio));

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

        // Identificar índices de columnas especiales
        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;
        $dniColIdx = $colsMap['DOCUMENTO DE IDENTIDAD'] ?? 35;

        // Datos Fila 5 en adelante
        $currentRow = 5;
        foreach ($filteredRows as $rData) {
            for ($col = 1; $col <= count($rData); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = $rData[$col - 1] ?? '';
                $outSheet->setCellValue("{$colLetter}{$currentRow}", $val);

                // Coloreado condicional para SITUACIÓN LABORAL (Col X / Col 24)
                if (($col - 1) === $situacionColIdx) {
                    $situacionStr = strtoupper(trim((string)$val));
                    if (str_contains($situacionStr, 'NOMBRADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9EAD3']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '274E13']],
                        ]);
                    } elseif (str_contains($situacionStr, 'CONTRATADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E1D5E7']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '4C1D95']],
                        ]);
                    } elseif (str_contains($situacionStr, 'VACANTE')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4CCCC']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '990000']],
                        ]);
                    } elseif (str_contains($situacionStr, 'DESIGNADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE5CD']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '783F04']],
                        ]);
                    }
                }

                // Coloreado condicional para DOCUMENTO DE IDENTIDAD en VACANTE (Col AJ)
                if (($col - 1) === $dniColIdx && strtoupper(trim((string)$val)) === 'VACANTE') {
                    $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4CCCC']],
                        'font' => ['bold' => true, 'color' => ['rgb' => '990000']],
                    ]);
                }
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
     * Exportación de Excel REPORTE MATRÍCULA individual (5 Secciones coloreadas y formato fiel).
     */
    public function exportarMatriculaColegio($fileSource, string $codigoColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();

        try {
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            $sourceSpreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $sourceSpreadsheet = IOFactory::load($filePath);
        }
        $sourceSheet = $sourceSpreadsheet->getActiveSheet();

        $highestRow = $sourceSheet->getHighestRow();
        $highestColString = $sourceSheet->getHighestColumn();
        $highestColNum = Coordinate::columnIndexFromString($highestColString);

        // Identificar la fila de cabeceras principales (ej. CÓDIGO IE, CENTRO EDUCATIVO)
        $headerRowIdx = 6;
        for ($r = 1; $r <= min(10, $highestRow); $r++) {
            $rowText = '';
            for ($c = 1; $c <= $highestColNum; $c++) {
                $val = $sourceSheet->getCell([$c, $r])->getValue();
                if ($val !== null) $rowText .= ' ' . strtoupper((string)$val);
            }
            if (str_contains($rowText, 'CÓDIGO IE') || str_contains($rowText, 'CODIGO IE') || str_contains($rowText, 'CENTRO EDUCATIVO')) {
                $headerRowIdx = $r;
                break;
            }
        }

        // Mapear columnas en la fila de cabecera
        $colsMap = [];
        for ($c = 1; $c <= $highestColNum; $c++) {
            $val = $sourceSheet->getCell([$c, $headerRowIdx])->getValue();
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $c;
            }
        }

        $ieCol = $colsMap['CÓDIGO IE'] ?? $colsMap['CODIGO IE'] ?? 5;
        $nombreCol = $colsMap['CENTRO EDUCATIVO'] ?? $colsMap['NOMBRE DE IE'] ?? 6;
        $codModCol = $colsMap['CÓDIGO MODULAR'] ?? $colsMap['CODIGO MODULAR'] ?? 4;

        // Encontrar filas de datos que pertenecen a este colegio
        $matchingRows = [];
        for ($r = $headerRowIdx + 1; $r <= $highestRow; $r++) {
            $codIeVal = trim((string)$sourceSheet->getCell([$ieCol, $r])->getValue());
            $nombreIeVal = trim((string)$sourceSheet->getCell([$nombreCol, $r])->getValue());
            $codModVal = trim((string)$sourceSheet->getCell([$codModCol, $r])->getValue());

            if ($codIeVal === '' && $nombreIeVal === '' && $codModVal === '') continue;

            if (
                strcasecmp($codIeVal, $codigoColegio) === 0 ||
                strcasecmp($nombreIeVal, $codigoColegio) === 0 ||
                strcasecmp($codModVal, $codigoColegio) === 0 ||
                ($codigoColegio !== '' && str_contains(strtoupper($codIeVal), strtoupper($codigoColegio))) ||
                ($codigoColegio !== '' && str_contains(strtoupper($nombreIeVal), strtoupper($codigoColegio)))
            ) {
                $matchingRows[] = $r;
            }
        }

        // Crear hoja de salida
        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();
        $outSheet->setTitle('REPORTE - I.E. ' . substr($codigoColegio, 0, 15));

        // 1. Copiar anchos de columna
        for ($c = 1; $c <= $highestColNum; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $dim = $sourceSheet->getColumnDimension($colLetter);
            if ($dim->getWidth() > 0) {
                $outSheet->getColumnDimension($colLetter)->setWidth($dim->getWidth());
            } else {
                $outSheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        // 2. Copiar celdas combinadas de la cabecera (filas 1 a $headerRowIdx)
        foreach ($sourceSheet->getMergeCells() as $mergeRange) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $mergeRange, $m)) {
                $endRow = (int)$m[4];
                if ($endRow <= $headerRowIdx) {
                    $outSheet->mergeCells($mergeRange);
                }
            }
        }

        // 3. Copiar contenido y estilos de las filas de cabecera (1 a $headerRowIdx)
        for ($r = 1; $r <= $headerRowIdx; $r++) {
            $rowDim = $sourceSheet->getRowDimension($r);
            if ($rowDim->getRowHeight() > 0) {
                $outSheet->getRowDimension($r)->setRowHeight($rowDim->getRowHeight());
            }
            for ($c = 1; $c <= $highestColNum; $c++) {
                $srcCell = $sourceSheet->getCell([$c, $r]);
                $dstCell = $outSheet->getCell([$c, $r]);
                $dstCell->setValue($srcCell->getValue());
                $outSheet->getStyle([$c, $r])->duplicate($sourceSheet->getStyle([$c, $r]));
            }
        }

        // 4. Copiar filas de datos filtradas
        $outRow = $headerRowIdx + 1;
        foreach ($matchingRows as $srcRow) {
            $rowDim = $sourceSheet->getRowDimension($srcRow);
            if ($rowDim->getRowHeight() > 0) {
                $outSheet->getRowDimension($outRow)->setRowHeight($rowDim->getRowHeight());
            } else {
                $outSheet->getRowDimension($outRow)->setRowHeight(20);
            }

            for ($c = 1; $c <= $highestColNum; $c++) {
                $srcCell = $sourceSheet->getCell([$c, $srcRow]);
                $dstCell = $outSheet->getCell([$c, $outRow]);
                $dstCell->setValue($srcCell->getValue());
                $outSheet->getStyle([$c, $outRow])->duplicate($sourceSheet->getStyle([$c, $srcRow]));
            }
            $outRow++;
        }

        // Guardar archivo
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

        if ($tipoExcel === 'NEXUS') {
            $zipFileName = 'REPORTES_NEXUS_ORGANIZADOS_POR_COLEGIO.zip';
            $basePath = 'REPORTES_NEXUS_ORGANIZADOS_POR_COLEGIO';
        } else {
            $zipFileName = 'REPORTES_ORGANIZADOS_POR_COLEGIO_Y_NIVEL.zip';
            $basePath = 'REPORTES_ORGANIZADOS_POR_COLEGIO_Y_NIVEL/COLEGIOS_UGEL_PIURA';
        }

        $zipPath = "{$outputDir}/{$zipFileName}";

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo ZIP.');
        }

        $generados = [];
        foreach ($colegios as $c) {
            $key = $c['nombre'] ?? $c['codigo_ie'] ?? $c['codigo_local'] ?? $c['codmod'] ?? null;
            if (!$key) continue;

            $codCode = !empty($c['codigo_local']) ? trim((string)$c['codigo_local']) : (!empty($c['codigo_ie']) ? trim((string)$c['codigo_ie']) : (!empty($c['codmod']) ? trim((string)$c['codmod']) : '000000'));
            if (strlen($codCode) < 6 && is_numeric($codCode)) {
                $codCode = str_pad($codCode, 6, '0', STR_PAD_LEFT);
            }
            $nombreIe = trim((string)($c['nombre'] ?? 'IE'));
            if ($nombreIe === '') $nombreIe = "IE_{$codCode}";

            $folderName = "{$codCode} - {$nombreIe}";
            $folderNameSafe = preg_replace('/[^\w\s\.-]/u', '_', $folderName);
            $folderNameSafe = preg_replace('/\s+/', ' ', trim($folderNameSafe));

            try {
                if ($tipoExcel === 'NEXUS') {
                    $excelRes = $this->exportarNexusColegio($fileSource, $key);
                } else {
                    $excelRes = $this->exportarMatriculaColegio($fileSource, $key);
                }

                if (file_exists($excelRes['path'])) {
                    $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$excelRes['filename']}";
                    $zip->addFile($excelRes['path'], $zipInternalPath);
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

        return [
            'path' => $zipPath,
            'filename' => $zipFileName,
        ];
    }
}

