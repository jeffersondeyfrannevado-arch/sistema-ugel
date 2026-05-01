<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MatriculaService
{
    /**
     * Columnas base del Excel de origen (indice 0 = col A).
     */
    private const COLS = [
        'DRE' => 0,
        'UGEL' => 1,
        'DEPARTAMENTO' => 2,
        'PROVINCIA' => 3,
        'DISTRITO' => 4,
        'CENTRO_POBLADO' => 5,
        'COD_MOD' => 6,
        'ANEXO' => 7,
        'NOMBRE_IE' => 8,
        'NIVEL' => 9,
        'MODALIDAD' => 10,
        'TIPO_IE' => 11,
        'TOTAL_MATRICULADOS' => 12,
        'MATRICULA_DEFINITIVA' => 14,
        'MATRICULA_EN_PROCESO' => 17,
        'DNI_VALIDADO' => 19,
        'DNI_SIN_VALIDAR' => 20,
        'SIN_DNI' => 21,
        'TOTAL_GRADOS' => 22,
        'TOTAL_SECCIONES' => 23,
        'NOM_GENERADAS' => 24,
        'NOM_APROBADAS' => 25,
        'NOM_RECTIFICAR' => 26,
    ];

    private const COMMON_HEADERS = [
        'DRE', 'UGEL', 'Departamento', 'Provincia', 'Distrito', 'Centro Poblado',
        'Cod. Mod.', 'Anexo', 'Nombre de IE', 'Nivel', 'Modalidad', 'Tipo IE',
        'Total Matriculados', 'Matricula Definitiva', 'Matricula En Proceso',
        'DNI Validado', 'DNI sin Validar', 'Sin DNI',
        'Total Grados', 'Total Secciones',
        'Nominas Generadas', 'Nominas Aprobadas', 'Nominas por Rectificar',
    ];

    private const COMMON_FIELDS = [
        'DRE', 'UGEL', 'DEPARTAMENTO', 'PROVINCIA', 'DISTRITO', 'CENTRO_POBLADO',
        'COD_MOD', 'ANEXO', 'NOMBRE_IE', 'NIVEL', 'MODALIDAD', 'TIPO_IE',
        'TOTAL_MATRICULADOS', 'MATRICULA_DEFINITIVA', 'MATRICULA_EN_PROCESO',
        'DNI_VALIDADO', 'DNI_SIN_VALIDAR', 'SIN_DNI',
        'TOTAL_GRADOS', 'TOTAL_SECCIONES',
        'NOM_GENERADAS', 'NOM_APROBADAS', 'NOM_RECTIFICAR',
    ];

    private const TIPOS_PUBLICOS = ['A1', 'A2', 'A4'];

    private const FORMATOS = [
        'SECUNDARIA' => [
            'titulo' => 'SECUNDARIA',
            'headers' => ['1ro H', '1ro M', '2do H', '2do M', '3ro H', '3ro M', '4to H', '4to M', '5to H', '5to M'],
            'fields' => ['P1H', 'P1M', 'P2H', 'P2M', 'P3H', 'P3M', 'P4H', 'P4M', 'P5H', 'P5M'],
            'columnas' => [
                'P1H' => 27, 'P1M' => 28,
                'P2H' => 29, 'P2M' => 30,
                'P3H' => 31, 'P3M' => 32,
                'P4H' => 33, 'P4M' => 34,
                'P5H' => 35, 'P5M' => 36,
            ],
        ],
        'INICIAL' => [
            'titulo' => 'INICIAL',
            'headers' => ['0 anos H', '0 anos M', '1 ano H', '1 ano M', '2 anos H', '2 anos M', '3 anos H', '3 anos M', '4 anos H', '4 anos M', '5 anos H', '5 anos M', 'Mas de 5 anos H', 'Mas de 5 anos M'],
            'fields' => ['E0H', 'E0M', 'E1H', 'E1M', 'E2H', 'E2M', 'E3H', 'E3M', 'E4H', 'E4M', 'E5H', 'E5M', 'EM5H', 'EM5M'],
            'columnas' => [
                'E0H' => 27, 'E0M' => 28,
                'E1H' => 29, 'E1M' => 30,
                'E2H' => 31, 'E2M' => 32,
                'E3H' => 33, 'E3M' => 34,
                'E4H' => 35, 'E4M' => 36,
                'E5H' => 37, 'E5M' => 38,
                'EM5H' => 39, 'EM5M' => 40,
            ],
        ],
    ];

    private string $outputBase;

    public function __construct()
    {
        $this->outputBase = storage_path('app/matricula_output');
    }

    public function procesarArchivo(UploadedFile $archivo, array $columnasResaltadas): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $this->limpiarDirectorio($this->outputBase);

        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        $dataStartRow = $this->encontrarFilaDatos($rawRows);
        $headerRowIndex = max(0, $dataStartRow - 2);
        $subHeaderRowIndex = max(0, $dataStartRow - 1);
        $formato = $this->detectarFormato(
            $rawRows[$headerRowIndex] ?? [],
            $rawRows[$subHeaderRowIndex] ?? [],
            $sheet->getTitle(),
            $rawRows[$dataStartRow][self::COLS['NIVEL']] ?? ''
        );
        $columnasDetectadas = $this->resolverColumnas(
            $rawRows[$headerRowIndex] ?? [],
            $rawRows[$subHeaderRowIndex] ?? []
        );

        $registros = [];
        $errores = [];

        for ($i = $dataStartRow; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];

            if ($this->filaVacia($row)) {
                continue;
            }

            $resultado = $this->limpiarYValidarFila($row, $i + 1, $formato, $columnasDetectadas);

            if ($resultado['valida']) {
                $registros[] = $resultado['datos'];
            } else {
                $errores[] = $resultado['error'];
            }
        }

        $publicos = array_filter($registros, fn ($r) => $r['_modalidad'] === 'PUBLICO');
        $privados = array_filter($registros, fn ($r) => $r['_modalidad'] === 'PRIVADO');

        $archivosGenerados = [];

        foreach (['PUBLICO' => $publicos, 'PRIVADO' => $privados] as $modalidad => $grupo) {
            if (empty($grupo)) {
                continue;
            }

            $porDistrito = $this->agruparPorDistrito($grupo);

            foreach ($porDistrito as $distrito => $filas) {
                $nombreArchivo = $this->generarExcelDistrito(
                    $modalidad,
                    $distrito,
                    $filas,
                    $columnasResaltadas,
                    $formato
                );

                $archivosGenerados[] = [
                    'modalidad' => $modalidad,
                    'distrito' => $distrito,
                    'nivel' => $formato['titulo'],
                    'archivo' => $nombreArchivo,
                    'ruta' => base64_encode("{$modalidad}/{$distrito}/{$nombreArchivo}"),
                    'registros' => count($filas),
                ];
            }
        }

        $estadisticas = [
            'total' => count($registros),
            'publicos' => count($publicos),
            'privados' => count($privados),
            'errores' => count($errores),
            'distritos' => count(array_unique(array_column($registros, 'DISTRITO'))),
        ];

        return [
            'nivel' => $formato['titulo'],
            'estadisticas' => $estadisticas,
            'archivos' => $archivosGenerados,
            'errores' => $errores,
        ];
    }

    public function previewArchivo(UploadedFile $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray('', true, true, false);

        $dataStartRow = $this->encontrarFilaDatos($rawRows);
        $headerRowIndex = max(0, $dataStartRow - 2);
        $subHeaderRowIndex = max(0, $dataStartRow - 1);
        $formato = $this->detectarFormato(
            $rawRows[$headerRowIndex] ?? [],
            $rawRows[$subHeaderRowIndex] ?? [],
            $sheet->getTitle(),
            $rawRows[$dataStartRow][self::COLS['NIVEL']] ?? ''
        );

        $headers = $this->buildPreviewHeaders(
            $rawRows[$headerRowIndex] ?? [],
            $rawRows[$subHeaderRowIndex] ?? []
        );

        $rows = [];
        foreach (array_slice($rawRows, $dataStartRow, 12) as $row) {
            if ($this->filaVacia($row)) {
                continue;
            }

            $rows[] = array_map(
                fn ($value) => $this->limpiarTexto((string) $value),
                array_slice($row, 0, count($headers))
            );
        }

        return [
            'sheet' => $sheet->getTitle(),
            'nivel' => $formato['titulo'],
            'fila_inicio_datos' => $dataStartRow + 1,
            'total_filas_excel' => count($rawRows),
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    public function generarPdfDesdeExcel(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray('', true, true, false);

        $title = $this->normalizarPdfTexto((string) ($rows[0][0] ?? 'REPORTE DE MATRICULA'));
        $totalRow = $rows[count($rows) - 1] ?? [];
        $registros = max(0, count($rows) - 3);

        $summaryLines = [
            $title,
            'Generado: ' . now()->format('Y-m-d H:i'),
            'Archivo origen: ' . basename($fullPath),
            'Registros incluidos: ' . $registros,
            'Totales -> Matriculados: ' . ($totalRow[12] ?? 0) . ' | En proceso: ' . ($totalRow[14] ?? 0) . ' | Secciones: ' . ($totalRow[19] ?? 0),
            '',
            $this->formatPdfLine('COD MOD', 'INSTITUCION EDUCATIVA', 'MAT', 'PROC', 'SEC'),
            str_repeat('-', 112),
        ];

        $detailLines = [];
        foreach (array_slice($rows, 2, max(0, count($rows) - 3)) as $row) {
            $detailLines[] = $this->formatPdfLine(
                (string) ($row[6] ?? ''),
                (string) ($row[8] ?? ''),
                (string) ($row[12] ?? '0'),
                (string) ($row[14] ?? '0'),
                (string) ($row[19] ?? '0')
            );
        }

        $allLines = array_merge($summaryLines, $detailLines);
        $pdfContent = $this->buildSimplePdf($allLines);
        $pdfName = pathinfo($fullPath, PATHINFO_FILENAME) . '.pdf';

        return [$pdfContent, $pdfName];
    }

    private function encontrarFilaDatos(array $rows): int
    {
        foreach ($rows as $idx => $row) {
            if (isset($row[0]) && strtoupper(trim((string) $row[0])) === 'DRE') {
                return $idx + 2;
            }
        }

        return 8;
    }

    private function filaVacia(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $v !== null && $v !== ''));
    }

    private function limpiarYValidarFila(array $row, int $lineaNum, array $formato, array $columnasDetectadas = []): array
    {
        $c = self::COLS;

        $tipoIE = trim((string) ($row[$c['TIPO_IE']] ?? ''));
        $nombreIE = trim((string) ($row[$c['NOMBRE_IE']] ?? ''));
        $distrito = trim((string) ($row[$c['DISTRITO']] ?? ''));
        $codMod = trim((string) ($row[$c['COD_MOD']] ?? ''));

        if ($nombreIE === '') {
            return ['valida' => false, 'error' => "Linea {$lineaNum}: Nombre de IE vacio"];
        }

        if ($distrito === '') {
            return ['valida' => false, 'error' => "Linea {$lineaNum}: Distrito vacio en IE '{$nombreIE}'"];
        }

        $codigoTipo = substr($tipoIE, 0, 2);
        $esPublico = in_array($codigoTipo, self::TIPOS_PUBLICOS, true);
        $modalidad = $esPublico ? 'PUBLICO' : 'PRIVADO';

        $datos = [
            'DRE' => $this->limpiarTexto($row[$c['DRE']] ?? ''),
            'UGEL' => $this->limpiarTexto($row[$c['UGEL']] ?? ''),
            'DEPARTAMENTO' => $this->normalizarTexto($row[$c['DEPARTAMENTO']] ?? ''),
            'PROVINCIA' => $this->normalizarTexto($row[$c['PROVINCIA']] ?? ''),
            'DISTRITO' => $this->normalizarTexto($distrito),
            'CENTRO_POBLADO' => $this->normalizarTexto($row[$c['CENTRO_POBLADO']] ?? ''),
            'COD_MOD' => $codMod,
            'ANEXO' => $this->limpiarNumero($row[$c['ANEXO']] ?? 0),
            'NOMBRE_IE' => mb_strtoupper(trim($nombreIE)),
            'NIVEL' => $this->limpiarTexto($row[$c['NIVEL']] ?? ''),
            'MODALIDAD' => $this->limpiarTexto($row[$c['MODALIDAD']] ?? ''),
            'TIPO_IE' => $tipoIE,
            'TOTAL_MATRICULADOS' => $this->limpiarNumero($row[$c['TOTAL_MATRICULADOS']] ?? 0),
            'MATRICULA_DEFINITIVA' => $this->limpiarNumero($row[$c['MATRICULA_DEFINITIVA']] ?? 0),
            'MATRICULA_EN_PROCESO' => $this->limpiarNumero(
                $this->obtenerValorCampo($row, 'MATRICULA_EN_PROCESO', $columnasDetectadas)
            ),
            'DNI_VALIDADO' => $this->limpiarNumero($row[$c['DNI_VALIDADO']] ?? 0),
            'DNI_SIN_VALIDAR' => $this->limpiarNumero($row[$c['DNI_SIN_VALIDAR']] ?? 0),
            'SIN_DNI' => $this->limpiarNumero($row[$c['SIN_DNI']] ?? 0),
            'TOTAL_GRADOS' => $this->limpiarNumero($row[$c['TOTAL_GRADOS']] ?? 0),
            'TOTAL_SECCIONES' => $this->limpiarNumero($row[$c['TOTAL_SECCIONES']] ?? 0),
            'NOM_GENERADAS' => $this->limpiarNumero($row[$c['NOM_GENERADAS']] ?? 0),
            'NOM_APROBADAS' => $this->limpiarNumero($row[$c['NOM_APROBADAS']] ?? 0),
            'NOM_RECTIFICAR' => $this->limpiarNumero($row[$c['NOM_RECTIFICAR']] ?? 0),
            '_modalidad' => $modalidad,
            '_nivel_reporte' => $formato['titulo'],
        ];

        foreach ($formato['columnas'] as $campo => $indice) {
            $datos[$campo] = $this->limpiarNumero($row[$indice] ?? 0);
        }

        return ['valida' => true, 'datos' => $datos];
    }

    private function obtenerValorCampo(array $row, string $campo, array $columnasDetectadas): mixed
    {
        $indice = $columnasDetectadas[$campo] ?? self::COLS[$campo] ?? null;
        if ($indice === null) {
            return 0;
        }

        return $row[$indice] ?? 0;
    }

    private function agruparPorDistrito(array $registros): array
    {
        $grupos = [];

        foreach ($registros as $registro) {
            $grupos[$registro['DISTRITO']][] = $registro;
        }

        return $grupos;
    }

    private function generarExcelDistrito(
        string $modalidad,
        string $distrito,
        array $filas,
        array $columnasResaltadas,
        array $formato
    ): string {
        usort($filas, function ($a, $b) {
            $va = $a['MATRICULA_EN_PROCESO'];
            $vb = $b['MATRICULA_EN_PROCESO'];

            if ($va === 0 && $vb === 0) {
                return 0;
            }
            if ($va === 0) {
                return 1;
            }
            if ($vb === 0) {
                return -1;
            }

            return $vb <=> $va;
        });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matricula');

        $cabeceras = array_merge(self::COMMON_HEADERS, $formato['headers']);
        $camposOrdenados = array_merge(self::COMMON_FIELDS, $formato['fields']);
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($cabeceras));

        $sheet->mergeCells("A1:{$ultimaColumna}1");
        $sheet->setCellValue('A1', "REPORTE DE MATRICULA - {$formato['titulo']} | {$modalidad} | DISTRITO: {$distrito}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $modalidad === 'PUBLICO' ? '1A56DB' : '7E22CE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        foreach ($cabeceras as $colIdx => $header) {
            $colLetra = Coordinate::stringFromColumnIndex($colIdx + 1);
            $cell = $colLetra . '2';
            $sheet->setCellValue($cell, $header);

            $bgColor = $modalidad === 'PUBLICO' ? 'DBEAFE' : 'EDE9FE';
            $styleArr = [
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ];

            if ($this->esColumnaResaltada($header, $columnasResaltadas)) {
                $styleArr['fill']['startColor']['rgb'] = $modalidad === 'PUBLICO' ? 'FEF08A' : 'FDE68A';
                $styleArr['font']['color'] = ['rgb' => '92400E'];
            }

            $sheet->getStyle($cell)->applyFromArray($styleArr);
            $sheet->getColumnDimension($colLetra)->setAutoSize(true);
        }

        $sheet->getRowDimension(2)->setRowHeight(35);

        foreach ($filas as $rowIdx => $fila) {
            $excelRow = $rowIdx + 3;
            $bgFila = $rowIdx % 2 === 0 ? 'FFFFFF' : ($modalidad === 'PUBLICO' ? 'EFF6FF' : 'F5F3FF');

            foreach ($camposOrdenados as $colIdx => $campo) {
                $colLetra = Coordinate::stringFromColumnIndex($colIdx + 1);
                $valor = $fila[$campo] ?? '';
                $sheet->setCellValue($colLetra . $excelRow, $valor);

                $styleArr = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgFila]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                    'font' => ['size' => 9],
                ];

                if ($campo === 'MATRICULA_EN_PROCESO') {
                    $styleArr['fill']['startColor']['rgb'] = $valor > 0 ? 'FEF9C3' : 'FFFFFF';
                    $styleArr['font']['bold'] = $valor > 0;

                    if ($valor > 10) {
                        $styleArr['font']['color'] = ['rgb' => 'B45309'];
                    }
                }

                $sheet->getStyle($colLetra . $excelRow)->applyFromArray($styleArr);
            }
        }

        $totalRow = count($filas) + 3;
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->getStyle('A' . $totalRow)->getFont()->setBold(true);

        $camposNumericos = array_slice($camposOrdenados, 12);
        foreach ($camposNumericos as $colIdx => $campo) {
            $col = Coordinate::stringFromColumnIndex($colIdx + 13);
            $suma = array_sum(array_column($filas, $campo));
            $sheet->setCellValue($col . $totalRow, $suma);
            $sheet->getStyle($col . $totalRow)->getFont()->setBold(true);
            $sheet->getStyle($col . $totalRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($modalidad === 'PUBLICO' ? 'BFDBFE' : 'DDD6FE');
        }

        foreach (range(1, count($cabeceras)) as $ci) {
            $cl = Coordinate::stringFromColumnIndex($ci);
            $sheet->getColumnDimension($cl)->setAutoSize(true);
        }

        $dirSalida = $this->outputBase . "/{$modalidad}/{$distrito}";
        if (!is_dir($dirSalida)) {
            mkdir($dirSalida, 0755, true);
        }

        $distritoSlug = $this->slugify($distrito);
        $nombreArchivo = "MATRICULA_{$formato['titulo']}_{$modalidad}_{$distritoSlug}.xlsx";
        $rutaCompleta = $dirSalida . '/' . $nombreArchivo;

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaCompleta);

        return $nombreArchivo;
    }

    private function limpiarTexto(mixed $v): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }

    private function normalizarTexto(mixed $v): string
    {
        return mb_strtoupper($this->limpiarTexto($v));
    }

    private function limpiarNumero(mixed $v): int
    {
        $n = (int) preg_replace('/[^0-9]/', '', (string) $v);

        return max(0, $n);
    }

    private function slugify(string $text): string
    {
        $text = $this->normalizarEncabezado($text);
        $text = preg_replace('/[^A-Z0-9]+/', '_', $text);

        return trim((string) $text, '_');
    }

    private function limpiarDirectorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
    }

    private function detectarFormato(array $headerRow, array $subHeaderRow, string $sheetTitle, mixed $nivelMuestra): array
    {
        $textoCabecera = $this->normalizarEncabezado(implode(' ', array_filter(
            array_merge($headerRow, $subHeaderRow, [$sheetTitle, (string) $nivelMuestra]),
            fn ($v) => $v !== null && $v !== ''
        )));

        if (
            str_contains($textoCabecera, 'INICIAL') ||
            str_contains($textoCabecera, '0 ANOS') ||
            str_contains($textoCabecera, '1 ANO') ||
            str_contains($textoCabecera, 'MAS DE 5 ANOS')
        ) {
            return self::FORMATOS['INICIAL'];
        }

        return self::FORMATOS['SECUNDARIA'];
    }

    private function esColumnaResaltada(string $header, array $columnasResaltadas): bool
    {
        $headerNormalizado = $this->normalizarEncabezado($header);

        if ($headerNormalizado === $this->normalizarEncabezado('Matricula En Proceso')) {
            return true;
        }

        foreach ($columnasResaltadas as $columna) {
            if ($headerNormalizado === $this->normalizarEncabezado((string) $columna)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarEncabezado(string $texto): string
    {
        $texto = trim($texto);
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = strtoupper($texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    private function resolverColumnas(array $headerRow, array $subHeaderRow): array
    {
        $aliases = [
            'MATRICULA_EN_PROCESO' => [
                'MATRICULA EN PROCESO',
                'EN PROCESO',
                'MATRICULA PROCESO',
            ],
        ];

        $mapa = [];
        $limit = max(count($headerRow), count($subHeaderRow));

        for ($i = 0; $i < $limit; $i++) {
            $main = $this->normalizarEncabezado((string) ($headerRow[$i] ?? ''));
            $sub = $this->normalizarEncabezado((string) ($subHeaderRow[$i] ?? ''));
            $combo = trim($main . ' ' . $sub);

            foreach ($aliases as $campo => $valores) {
                if (isset($mapa[$campo])) {
                    continue;
                }

                foreach ($valores as $alias) {
                    $aliasNorm = $this->normalizarEncabezado($alias);
                    if (
                        $main === $aliasNorm ||
                        $sub === $aliasNorm ||
                        str_contains($combo, $aliasNorm)
                    ) {
                        $mapa[$campo] = $i;
                        break;
                    }
                }
            }
        }

        return $mapa;
    }

    private function buildPreviewHeaders(array $headerRow, array $subHeaderRow): array
    {
        $limit = max(count($headerRow), count($subHeaderRow));
        $headers = [];

        for ($i = 0; $i < $limit; $i++) {
            $main = $this->limpiarTexto((string) ($headerRow[$i] ?? ''));
            $sub = $this->limpiarTexto((string) ($subHeaderRow[$i] ?? ''));

            if ($main !== '' && $sub !== '') {
                $headers[] = "{$main} - {$sub}";
            } elseif ($main !== '') {
                $headers[] = $main;
            } elseif ($sub !== '') {
                $headers[] = $sub;
            } else {
                $headers[] = 'Columna ' . ($i + 1);
            }
        }

        return $headers;
    }

    private function formatPdfLine(string $codMod, string $nombreIe, string $matriculados, string $enProceso, string $secciones): string
    {
        return sprintf(
            "%-12s %-68s %8s %8s %8s",
            $this->fitPdfText($codMod, 12),
            $this->fitPdfText($nombreIe, 68),
            $this->fitPdfText($matriculados, 8),
            $this->fitPdfText($enProceso, 8),
            $this->fitPdfText($secciones, 8)
        );
    }

    private function fitPdfText(string $text, int $length): string
    {
        $text = $this->normalizarPdfTexto($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, max(0, $length - 3)) . '...';
    }

    private function normalizarPdfTexto(string $text): string
    {
        $text = $this->limpiarTexto($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;
    }

    private function buildSimplePdf(array $lines): string
    {
        $maxLinesPerPage = 48;
        $pages = array_chunk($lines, $maxLinesPerPage);
        $objects = [];

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        $pageObjectNumbers = [];
        $contentObjectNumbers = [];

        $nextObjectNumber = 3;
        foreach ($pages as $pageIndex => $pageLines) {
            $pageObjectNumbers[$pageIndex] = $nextObjectNumber++;
            $contentObjectNumbers[$pageIndex] = $nextObjectNumber++;
            $kids[] = $pageObjectNumbers[$pageIndex] . " 0 R";
        }

        $objects[] = "<< /Type /Pages /Count " . count($pages) . " /Kids [" . implode(' ', $kids) . "] >>";

        foreach ($pages as $pageIndex => $pageLines) {
            $content = "BT\n/F1 9 Tf\n36 806 Td\n12 TL\n";
            foreach ($pageLines as $line) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $content .= '(' . $escaped . ") Tj\nT*\n";
            }
            $content .= "ET";

            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Courier >> >> >> /Contents " . $contentObjectNumbers[$pageIndex] . " 0 R >>";
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
