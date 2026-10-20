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
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $spreadsheet = IOFactory::load($filePath);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        try {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\Throwable $e) {}

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
     * Construye y guarda el archivo Excel NEXUS para un colegio dado a partir de sus filas ya filtradas.
     * Aplica el Banner Azul oficial (Filas 1-3), cabecera oscura (Fila 4) y coloreado condicional oficial.
     */
    public function generarExcelNexusColegioDesdeFilas(string $nombreColegio, array $filteredRows, array $headers, array $colsMap = []): array
    {
        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();
        $safeSheetTitle = 'NEXUS - ' . substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $nombreColegio), 0, 20);
        $outSheet->setTitle($safeSheetTitle);

        $totalCols = max(66, count($headers));
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        // Banner Azul Superior en Filas 1, 2 y 3 (Idéntico a las capturas oficiales)
        $outSheet->mergeCells("A1:{$lastColLetter}1");
        $outSheet->mergeCells("A2:{$lastColLetter}2");
        $outSheet->mergeCells("A3:{$lastColLetter}3");

        $outSheet->setCellValue('A2', 'GOBIERNO REGIONAL DE PIURA - UNIDAD DE GESTION EDUCATIVA LOCAL PIURA');
        $outSheet->setCellValue('A3', 'CUADRO DE PLAZAS NEXUS - I.E. ' . strtoupper($nombreColegio));

        $bannerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $outSheet->getStyle("A1:{$lastColLetter}3")->applyFromArray($bannerStyle);
        $outSheet->getRowDimension(1)->setRowHeight(18);
        $outSheet->getRowDimension(2)->setRowHeight(22);
        $outSheet->getRowDimension(3)->setRowHeight(22);

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
        $outSheet->getRowDimension(4)->setRowHeight(32);

        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;
        $dniColIdx = $colsMap['DOCUMENTO DE IDENTIDAD'] ?? 35;

        // Datos Fila 5 en adelante
        $currentRow = 5;
        foreach ($filteredRows as $rData) {
            for ($col = 1; $col <= count($rData); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = $rData[$col - 1] ?? '';
                $cIdx = $col - 1;
                $headerName = strtoupper(trim((string)($headers[$cIdx] ?? '')));

                // Formatear fechas legibles si es timestamp serial numérico de Excel
                if (str_contains($headerName, 'FECHA') && is_numeric($val) && $val > 1000 && $val < 100000) {
                    try {
                        $val = Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                    } catch (\Throwable $e) {}
                }

                $outSheet->setCellValue("{$colLetter}{$currentRow}", $val);

                // Coloreado condicional para SITUACIÓN LABORAL (Col X / Col 24)
                if ($cIdx === $situacionColIdx) {
                    $situacionStr = strtoupper(trim((string)$val));
                    if (str_contains($situacionStr, 'NOMBRADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '155724']],
                        ]);
                    } elseif (str_contains($situacionStr, 'CONTRATADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8DAEF']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '6C3483']],
                        ]);
                    } elseif (str_contains($situacionStr, 'VACANTE')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8D7DA']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '721C24']],
                        ]);
                    } elseif (str_contains($situacionStr, 'DESIGNADO')) {
                        $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE6CC']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '8A4B08']],
                        ]);
                    }
                }

                // Coloreado condicional para DOCUMENTO DE IDENTIDAD en VACANTE (Col AJ)
                if ($cIdx === $dniColIdx && strtoupper(trim((string)$val)) === 'VACANTE') {
                    $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8D7DA']],
                        'font' => ['bold' => true, 'color' => ['rgb' => '721C24']],
                    ]);
                }
            }

            $outSheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $outSheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        // Anchos de columna predeterminados para máxima velocidad y legibilidad oficial
        $outSheet->getDefaultColumnDimension()->setWidth(14);
        $outSheet->getColumnDimension('A')->setWidth(18); // REGION
        $outSheet->getColumnDimension('B')->setWidth(26); // UNIDAD EJECUTORA
        $outSheet->getColumnDimension('C')->setWidth(20); // ORGANO INTERMEDIO
        $outSheet->getColumnDimension('G')->setWidth(18); // GESTION
        $outSheet->getColumnDimension('I')->setWidth(14); // CODMOD
        $outSheet->getColumnDimension('L')->setWidth(14); // CODIGO LOCAL
        $outSheet->getColumnDimension('P')->setWidth(34); // NOMBRE DE LA INSTITUCION EDUCATIVA
        $outSheet->getColumnDimension('S')->setWidth(16); // CODIGO PLAZA
        $outSheet->getColumnDimension('X')->setWidth(20); // SITUACION LABORAL
        $outSheet->getColumnDimension('Y')->setWidth(32); // MOTIVO DE VACANTE
        $outSheet->getColumnDimension('AB')->setWidth(22); // CARGO
        $outSheet->getColumnDimension('AJ')->setWidth(16); // DOCUMENTO DE IDENTIDAD
        $outSheet->getColumnDimension('AP')->setWidth(20); // APELLIDO PATERNO
        $outSheet->getColumnDimension('AQ')->setWidth(20); // APELLIDO MATERNO
        $outSheet->getColumnDimension('AR')->setWidth(22); // NOMBRES

        $outputDir = $this->getWritableDir('nexus_output');
        $safeName = preg_replace('/[^\w\s\.-]/u', '_', $nombreColegio);
        $safeName = preg_replace('/\s+/', ' ', trim($safeName));
        if ($safeName === '') $safeName = 'IE';
        $filename = "NEXUS - {$safeName}.xlsx";
        $fullPath = "{$outputDir}/{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->save($fullPath);

        $outSpreadsheet->disconnectWorksheets();
        unset($outSpreadsheet, $writer);

        return [
            'filename' => $filename,
            'path' => $fullPath,
            'registros' => count($filteredRows),
        ];
    }

    /**
     * Exportación de Excel NEXUS individual (Banner azul en filas 1-3).
     */
    public function exportarNexusColegio($fileSource, string $nombreColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
        try {
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $sourceSpreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $sourceSpreadsheet = IOFactory::load($filePath);
        }
        $sourceSheet = $sourceSpreadsheet->getActiveSheet();
        $rows = $sourceSheet->toArray(null, true, true, false);

        try {
            $sourceSpreadsheet->disconnectWorksheets();
            unset($sourceSpreadsheet);
        } catch (\Throwable $e) {}

        $headerRowIdx = -1;
        for ($idx = 0; $idx < min(12, count($rows)); $idx++) {
            $rowStr = strtoupper(implode(' ', array_filter($rows[$idx] ?? [], fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) $headerRowIdx = 4;

        $headers = $rows[$headerRowIdx] ?? [];
        $colsMap = [];
        foreach ($headers as $cIdx => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[strtoupper(trim((string)$val))] = $cIdx;
            }
        }

        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8;
        $codLocalColIdx = $colsMap['CODIGO LOCAL'] ?? 11;

        $filteredRows = [];
        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));

            if (
                strcasecmp($nombreIe, $nombreColegio) === 0 ||
                strcasecmp($codMod, $nombreColegio) === 0 ||
                strcasecmp($codLocal, $nombreColegio) === 0 ||
                str_contains(strtoupper($nombreIe), strtoupper($nombreColegio))
            ) {
                $filteredRows[] = $row;
            }
        }

        return $this->generarExcelNexusColegioDesdeFilas($nombreColegio, $filteredRows, $headers, $colsMap);
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
                try {
                    $outSheet->duplicateStyle($sourceSheet->getStyle([$c, $r]), $dstCell->getCoordinate());
                } catch (\Throwable $e) {}
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
                try {
                    $outSheet->duplicateStyle($sourceSheet->getStyle([$c, $srcRow]), $dstCell->getCoordinate());
                } catch (\Throwable $e) {}
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

        if ($tipoExcel === 'NEXUS') {
            @set_time_limit(600);
            @ini_set('memory_limit', '512M');

            $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
            try {
                $readerType = IOFactory::identify($filePath);
                $reader = IOFactory::createReader($readerType);
                if (method_exists($reader, 'setReadDataOnly')) {
                    $reader->setReadDataOnly(true);
                }
                $sourceSpreadsheet = $reader->load($filePath);
            } catch (\Throwable $e) {
                $sourceSpreadsheet = IOFactory::load($filePath);
            }
            $sourceSheet = $sourceSpreadsheet->getActiveSheet();
            $rows = $sourceSheet->toArray(null, true, true, false);

            try {
                $sourceSpreadsheet->disconnectWorksheets();
                unset($sourceSpreadsheet);
            } catch (\Throwable $e) {}

            $headerRowIdx = -1;
            for ($idx = 0; $idx < min(12, count($rows)); $idx++) {
                $rowStr = strtoupper(implode(' ', array_filter($rows[$idx] ?? [], fn($v) => $v !== null)));
                if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                    $headerRowIdx = $idx;
                    break;
                }
            }
            if ($headerRowIdx === -1) $headerRowIdx = 4;

            $headers = $rows[$headerRowIdx] ?? [];
            $colsMap = [];
            foreach ($headers as $cIdx => $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $colsMap[strtoupper(trim((string)$val))] = $cIdx;
                }
            }

            $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? 15;
            $codModColIdx = $colsMap['CODMOD I.E.'] ?? 8;
            $codLocalColIdx = $colsMap['CODIGO LOCAL'] ?? 11;

            // Agrupar filas de datos en memoria por colegio en una única pasada
            $colegiosRowsMap = [];
            for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

                $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
                $codMod = trim((string)($row[$codModColIdx] ?? ''));

                $groupKey = $nombreIe !== '' ? $nombreIe : ($codMod !== '' ? "IE_{$codMod}" : 'COLEGIO_SIN_NOMBRE');
                $colegiosRowsMap[$groupKey][] = $row;
            }

            // Generar cada archivo individual para el ZIP sin sobrecargar memoria
            foreach ($colegiosRowsMap as $nombreColegio => $schoolRows) {
                $codLocal = '000000';
                foreach ($schoolRows as $sr) {
                    $clVal = trim((string)($sr[$codLocalColIdx] ?? ''));
                    if ($clVal !== '' && $clVal !== '0') {
                        $codLocal = $clVal;
                        break;
                    }
                }
                if (strlen($codLocal) < 6 && is_numeric($codLocal)) {
                    $codLocal = str_pad($codLocal, 6, '0', STR_PAD_LEFT);
                }

                $folderName = "{$codLocal} - {$nombreColegio}";
                $folderNameSafe = preg_replace('/[^\w\s\.-]/u', '_', $folderName);
                $folderNameSafe = preg_replace('/\s+/', ' ', trim($folderNameSafe));

                try {
                    $excelRes = $this->generarExcelNexusColegioDesdeFilas($nombreColegio, $schoolRows, $headers, $colsMap);
                    if (file_exists($excelRes['path'])) {
                        $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$excelRes['filename']}";
                        $zip->addFile($excelRes['path'], $zipInternalPath);
                        $generados[] = $excelRes['path'];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        } else {
            // Flujo original para Reporte de Matrícula (3 Niveles)
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
                    $excelRes = $this->exportarMatriculaColegio($fileSource, $key);
                    if (file_exists($excelRes['path'])) {
                        $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$excelRes['filename']}";
                        $zip->addFile($excelRes['path'], $zipInternalPath);
                        $generados[] = $excelRes['path'];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
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

