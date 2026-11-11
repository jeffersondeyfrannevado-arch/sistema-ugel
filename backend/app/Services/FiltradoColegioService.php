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
    private const MATRICULA_OFFICIAL_MERGES = [
        'B2:BN2',
        'B4:N4', 'P4:AR4', 'AS4:AV4', 'AW4:BK4', 'BL4:BO4',
        'B5:N5', 'P5:R5', 'S5:U5', 'V5:X5', 'Y5:AA5', 'AB5:AD5', 'AE5:AG5', 'AH5:AJ5', 'AK5:AQ5', 'AS5:AV5', 'AW5:BK5', 'BL5:BO5',
        'BN6:BO6',
    ];

    private const MATRICULA_OFFICIAL_WIDTHS = [
        1 => 1.71, 2 => 17.43, 3 => 14.57, 4 => 9.14, 5 => 9.14, 6 => 13.43, 7 => 22.71, 8 => 9.14, 9 => 3.86,
        10 => 5.14, 11 => 6.86, 12 => 7.29, 13 => 4.71, 14 => 24.86, 15 => 13.43, 16 => 5.14, 17 => 5.14, 18 => 4.43,
        19 => 5.57, 20 => 6.29, 21 => 5.43, 22 => 5.71, 23 => 5.0, 24 => 5.71, 25 => 6.0, 26 => 6.0, 27 => 5.29,
        28 => 5.86, 29 => 6.43, 30 => 5.71, 31 => 5.14, 32 => 4.86, 33 => 5.14, 34 => 6.14, 35 => 5.43, 36 => 6.29,
        37 => 5.29, 38 => 5.43, 39 => 4.86, 40 => 4.43, 41 => 5.86, 42 => 5.71, 43 => 6.0, 44 => 6.71, 45 => 6.86,
        46 => 6.57, 47 => 8.14, 48 => 40.71, 49 => 4.71, 50 => 4.14, 51 => 6.14, 52 => 4.57, 53 => 4.86, 54 => 4.57,
        55 => 7.86, 56 => 5.14, 57 => 9.14, 58 => 7.71, 59 => 5.86, 60 => 6.43, 61 => 4.86, 62 => 6.71, 63 => 9.71,
        64 => 10.14, 65 => 10.14, 66 => 11.71, 67 => 1.71, 68 => 11.71,
    ];

    private const NEXUS_OFFICIAL_WIDTHS = [
        'A' => 23, 'B' => 33, 'C' => 32, 'D' => 16, 'E' => 16, 'F' => 24, 'G' => 16, 'H' => 19, 'I' => 16,
        'J' => 17, 'K' => 18, 'L' => 16, 'M' => 16, 'N' => 16, 'O' => 20, 'P' => 38, 'Q' => 16, 'R' => 19,
        'S' => 17, 'T' => 16, 'U' => 22, 'V' => 26, 'W' => 24, 'X' => 21, 'Y' => 34, 'Z' => 26, 'AA' => 20,
        'AB' => 24, 'AC' => 18, 'AD' => 22, 'AE' => 16, 'AF' => 16, 'AG' => 20, 'AH' => 16, 'AI' => 22, 'AJ' => 22,
        'AK' => 18, 'AL' => 18, 'AM' => 16, 'AN' => 22, 'AO' => 22, 'AP' => 24, 'AQ' => 22, 'AR' => 18, 'AS' => 18,
        'AT' => 18, 'AU' => 20, 'AV' => 18, 'AW' => 18, 'AX' => 22, 'AY' => 36, 'AZ' => 26, 'BA' => 22, 'BB' => 32,
        'BC' => 18, 'BD' => 18, 'BE' => 26, 'BF' => 18, 'BG' => 22, 'BH' => 18, 'BI' => 24, 'BJ' => 24, 'BK' => 16,
        'BL' => 24, 'BM' => 20, 'BN' => 20,
    ];

    private function getWritableDir(string $subfolder): string
    {
        $dir = storage_path("app/{$subfolder}");
        if (@is_dir($dir) || @mkdir($dir, 0755, true)) {
            return $dir;
        }
        $tmpDir = sys_get_temp_dir() . "/{$subfolder}";
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        return $tmpDir;
    }

    /**
     * Limpia archivos antiguos de directorios temporales para evitar acumulación en disco/RAM (tmpfs en Render).
     */
    private function cleanTempFilesInDir(string $dir, int $olderThanSeconds = 120): void
    {
        if (!@is_dir($dir)) {
            return;
        }
        $now = time();
        foreach (@glob("{$dir}/*") ?: [] as $file) {
            if (@is_file($file) && ($olderThanSeconds <= 0 || ($now - (int)@filemtime($file)) >= $olderThanSeconds)) {
                @unlink($file);
            }
        }
    }

    /**
     * Lee todas las filas de un archivo Excel (.xlsx o .xls) con consumo mínimo de memoria.
     * Usa streaming XML nativo para .xlsx y modo ReadDataOnly sin estilos para .xls.
     */
    public function leerFilasExcelOptimizado(string $filePath): array
    {
        $rows = null;

        // Intentar lectura ultraligera streaming si es un contenedor ZIP (.xlsx)
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            try {
                $rows = $this->leerFilasStreamingXlsx($filePath);
            } catch (\Throwable $e) {
                $rows = null;
            }
        }

        // Fallback a PhpSpreadsheet en modo ultraligero (DataOnly + sin celdas vacías + sin formateo pesado)
        if ($rows === null) {
            $spreadsheet = null;
            $reader = null;
            try {
                $readerType = IOFactory::identify($filePath);
                $reader = IOFactory::createReader($readerType);
                if (method_exists($reader, 'setReadDataOnly')) {
                    $reader->setReadDataOnly(true);
                }
                if (method_exists($reader, 'setReadEmptyCells')) {
                    $reader->setReadEmptyCells(false);
                }
                $spreadsheet = $reader->load($filePath);
            } catch (\Throwable $e) {
                $spreadsheet = IOFactory::load($filePath);
            }

            $sheet = $spreadsheet->getActiveSheet();
            // Usar calculateFormulas=false y formatData=false reduce drásticamente el consumo de RAM y CPU
            $rows = $sheet->toArray(null, false, false, false);

            try {
                $spreadsheet->disconnectWorksheets();
            } catch (\Throwable $e) {}
            unset($sheet, $spreadsheet, $reader);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Detecta si un conjunto de filas pertenece a NEXUS o a MATRICULA.
     */
    private function esArchivoNexus(array $rows): bool
    {
        $textBuffer = '';
        $maxInspect = min(10, count($rows));
        for ($i = 0; $i < $maxInspect; $i++) {
            if (isset($rows[$i]) && is_array($rows[$i])) {
                $rowStr = implode(' ', array_filter($rows[$i], fn($v) => $v !== null && trim((string)$v) !== ''));
                $textBuffer .= ' ' . strtoupper($rowStr);
            }
        }

        return (
            str_contains($textBuffer, 'CODIGO DE PLAZA') ||
            str_contains($textBuffer, 'CUADRO DE PLAZAS NEXUS') ||
            str_contains($textBuffer, 'SITUACION LABORAL') ||
            str_contains($textBuffer, 'NOMBRE DE LA UNIDAD EJECUTORA') ||
            (str_contains($textBuffer, 'CODMOD I.E.') && str_contains($textBuffer, 'MOTIVO DE VACANTE'))
        );
    }

    /**
     * Procesa un archivo subido en el módulo aislado de filtrado por colegio.
     * Auto-detecta si es NEXUS o REPORTE MATRÍCULA y extrae la lista de colegios.
     */
    public function procesarArchivoColegios($file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        // Limpiar archivos temporales antiguos para liberar espacio en el contenedor
        $this->cleanTempFilesInDir($this->getWritableDir('matricula_colegios'), 60);
        $this->cleanTempFilesInDir($this->getWritableDir('nexus_output'), 60);
        $this->cleanTempFilesInDir($this->getWritableDir('temp_zips'), 60);

        $rows = $this->leerFilasExcelOptimizado($filePath);
        $esNexus = $this->esArchivoNexus($rows);
        $tipoExcel = $esNexus ? 'NEXUS' : 'MATRICULA';

        if ($esNexus) {
            $data = $this->extraerColegiosNexus($rows);
        } else {
            $data = $this->extraerColegiosMatricula($rows);
        }

        unset($rows);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
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
        $headerRowIdx = 4;

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

        $regionColIdx = $colsMap['NOMBRE DE LA REGION'] ?? 0;
        $ejecutoraColIdx = $colsMap['NOMBRE DE LA UNIDAD EJECUTORA'] ?? 1;
        $organoColIdx = $colsMap['NOMBRE DEL ORGANO INTERMEDIO'] ?? 2;
        $provinciaColIdx = $colsMap['PROVINCIA'] ?? 3;
        $distritoColIdx = $colsMap['DISTRITO'] ?? 4;
        $tipoIeColIdx = $colsMap['TIPO I.E.'] ?? 5;
        $gestionColIdx = $colsMap['GESTION'] ?? 6;
        $zonaColIdx = $colsMap['ZONA'] ?? 7;
        $codModColIdx = $colsMap['CODMOD I.E.'] ?? $colsMap['CODIGO MODULAR'] ?? 8;
        $codLocalColIdx = $colsMap['CODIGO LOCAL'] ?? 11;
        $clave8ColIdx = $colsMap['CLAVE8'] ?? 12;
        $nivelColIdx = $colsMap['NIVEL EDUCATIVO'] ?? 14;
        $ieColIdx = $colsMap['NOMBRE DE LA INSTITUCION EDUCATIVA'] ?? $colsMap['INSTITUCION EDUCATIVA'] ?? 15;
        $jecColIdx = $colsMap['JEC'] ?? 16;
        $plazaColIdx = $colsMap['CODIGO DE PLAZA'] ?? 17;
        $airhspColIdx = $colsMap['CODIGO AIRHSP'] ?? 18;
        $especialidadColIdx = $colsMap['ESPECIALIDAD'] ?? 19;
        $tipoTrabajadorColIdx = $colsMap['TIPO DE TRABAJADOR'] ?? 20;
        $subtipoTrabajadorColIdx = $colsMap['SUB-TIPO DE TRABAJADOR'] ?? 21;
        $cargoColIdx = $colsMap['CARGO'] ?? 22;
        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;
        $motivoColIdx = $colsMap['MOTIVO DE VACANTE'] ?? 24;
        $escalaColIdx = $colsMap['DESCRIPCION ESCALA'] ?? 25;
        $escalaHistorialColIdx = $colsMap['ESCALA HISTORIAL'] ?? 26;
        $categoriaRemunerativaColIdx = $colsMap['CATEGORIA REMUNERATIVA'] ?? 27;
        $jornadaColIdx = $colsMap['JORNADA LABORAL'] ?? 28;
        $estadoColIdx = $colsMap['ESTADO'] ?? 29;
        $fechaInicioColIdx = $colsMap['FECHA DE INICIO'] ?? 30;
        $fechaTerminoColIdx = $colsMap['FECHA DE TERMINO'] ?? 31;
        $tipoRegistroColIdx = $colsMap['TIPO DE REGISTRO'] ?? 32;
        $leyColIdx = $colsMap['LEY'] ?? 33;
        $fechaIngresoColIdx = $colsMap['FECHA DE INGRESO NOMB.'] ?? 34;
        $dniColIdx = $colsMap['DOCUMENTO DE IDENTIDAD'] ?? 35;
        $codigoModularColIdx = $colsMap['CODIGO MODULAR'] ?? 36;
        $fechaNacimientoColIdx = $colsMap['FECHA DE NACIMIENTO'] ?? 37;
        $sexoColIdx = $colsMap['SEXO'] ?? 38;
        $paternoColIdx = $colsMap['APELLIDO PATERNO'] ?? 39;
        $maternoColIdx = $colsMap['APELLIDO MATERNO'] ?? 40;
        $nombresColIdx = $colsMap['NOMBRES'] ?? 41;
        $regimenColIdx = $colsMap['REGIMEN PENSIONARIO'] ?? 42;

        $colegiosMap = [];
        $totalPlazas = 0;
        $nombrados = 0;
        $contratados = 0;
        $vacantes = 0;
        $designados = 0;

        $formatDateVal = static function ($val): string {
            if ($val === null || trim((string)$val) === '' || $val === '0') {
                return '';
            }
            if (is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
                try {
                    return Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                } catch (\Throwable $e) {}
            }
            return trim((string)$val);
        };

        $rowCount = count($rows);
        for ($i = $headerRowIdx + 1; $i < $rowCount; $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));
            $nivel = trim((string)($row[$nivelColIdx] ?? ''));
            $situacion = strtoupper(trim((string)($row[$situacionColIdx] ?? '')));

            if ($nombreIe === '' && $codMod === '' && $codLocal === '') {
                continue;
            }

            $key = $nombreIe !== '' ? $nombreIe : ($codLocal !== '' && $codLocal !== '0' ? "LOCAL_{$codLocal}" : "MOD_{$codMod}");

            if (!isset($colegiosMap[$key])) {
                $colegiosMap[$key] = [
                    'key' => $key,
                    'nombre' => $nombreIe ?: "IE {$codLocal}",
                    'codigo_local' => ($codLocal !== '' && $codLocal !== '0') ? $codLocal : '',
                    'codigo_ie' => ($codLocal !== '' && $codLocal !== '0') ? $codLocal : $codMod,
                    'codmod' => $codMod,
                    'plazas' => 0,
                    'nombrados' => 0,
                    'contratados' => 0,
                    'vacantes' => 0,
                    'designados' => 0,
                    'niveles' => [],
                    'codmods' => [],
                    'filas_detalle' => [],
                ];
            }

            $colegiosMap[$key]['plazas']++;
            $totalPlazas++;

            if ($nivel !== '' && !in_array($nivel, $colegiosMap[$key]['niveles'], true)) {
                $colegiosMap[$key]['niveles'][] = $nivel;
            }
            if ($codMod !== '' && !in_array($codMod, $colegiosMap[$key]['codmods'], true)) {
                $colegiosMap[$key]['codmods'][] = $codMod;
            }
            if ($codLocal !== '' && $codLocal !== '0' && empty($colegiosMap[$key]['codigo_local'])) {
                $colegiosMap[$key]['codigo_local'] = $codLocal;
                $colegiosMap[$key]['codigo_ie'] = $codLocal;
            }

            if (str_contains($situacion, 'NOMBRADO')) {
                $nombrados++;
                $colegiosMap[$key]['nombrados']++;
            } elseif (str_contains($situacion, 'CONTRATADO')) {
                $contratados++;
                $colegiosMap[$key]['contratados']++;
            } elseif (str_contains($situacion, 'VACANTE')) {
                $vacantes++;
                $colegiosMap[$key]['vacantes']++;
            } elseif (str_contains($situacion, 'DESIGNADO')) {
                $designados++;
                $colegiosMap[$key]['designados']++;
            }

            $dniVal = trim((string)($row[$dniColIdx] ?? ''));
            if (str_contains($situacion, 'VACANTE') && ($dniVal === '' || $dniVal === '0')) {
                $dniVal = 'VACANTE';
            }

            // Guardar filas de detalle para la vista previa interactiva en el frontend (hasta 80 filas por IE)
            if (count($colegiosMap[$key]['filas_detalle']) < 80) {
                $colegiosMap[$key]['filas_detalle'][] = [
                    'fila_origen' => $i + 1,
                    'region' => trim((string)($row[$regionColIdx] ?? 'PIURA')),
                    'ejecutora' => trim((string)($row[$ejecutoraColIdx] ?? 'EDUCACION PIURA')),
                    'organo' => trim((string)($row[$organoColIdx] ?? 'UGEL PIURA')),
                    'provincia' => trim((string)($row[$provinciaColIdx] ?? '')),
                    'distrito' => trim((string)($row[$distritoColIdx] ?? '')),
                    'tipo_ie' => trim((string)($row[$tipoIeColIdx] ?? '')),
                    'gestion' => trim((string)($row[$gestionColIdx] ?? 'ESTATAL')),
                    'zona' => trim((string)($row[$zonaColIdx] ?? 'URBANO / NO FRONTERA')),
                    'cod_mod' => $codMod,
                    'cod_local' => $codLocal,
                    'clave8' => trim((string)($row[$clave8ColIdx] ?? '')),
                    'nivel' => $nivel ?: 'REGULAR',
                    'nombre' => $nombreIe,
                    'jec' => trim((string)($row[$jecColIdx] ?? 'NO')),
                    'codigo_plaza' => trim((string)($row[$plazaColIdx] ?? '')),
                    'codigo_airhsp' => trim((string)($row[$airhspColIdx] ?? '')),
                    'especialidad' => trim((string)($row[$especialidadColIdx] ?? '')),
                    'tipo_trabajador' => trim((string)($row[$tipoTrabajadorColIdx] ?? 'DOCENTE')),
                    'subtipo_trabajador' => trim((string)($row[$subtipoTrabajadorColIdx] ?? '')),
                    'cargo' => trim((string)($row[$cargoColIdx] ?? '')),
                    'situacion' => $situacion,
                    'motivo_vacante' => trim((string)($row[$motivoColIdx] ?? '')),
                    'escala' => trim((string)($row[$escalaColIdx] ?? '')),
                    'escala_historial' => trim((string)($row[$escalaHistorialColIdx] ?? '')),
                    'categoria_remunerativa' => trim((string)($row[$categoriaRemunerativaColIdx] ?? '')),
                    'jornada' => trim((string)($row[$jornadaColIdx] ?? '')),
                    'estado' => trim((string)($row[$estadoColIdx] ?? '')),
                    'fecha_inicio' => $formatDateVal($row[$fechaInicioColIdx] ?? null),
                    'fecha_termino' => $formatDateVal($row[$fechaTerminoColIdx] ?? null),
                    'tipo_registro' => trim((string)($row[$tipoRegistroColIdx] ?? '')),
                    'ley' => trim((string)($row[$leyColIdx] ?? '')),
                    'fecha_ingreso' => $formatDateVal($row[$fechaIngresoColIdx] ?? null),
                    'dni' => $dniVal,
                    'codigo_modular' => trim((string)($row[$codigoModularColIdx] ?? '')),
                    'fecha_nacimiento' => $formatDateVal($row[$fechaNacimientoColIdx] ?? null),
                    'sexo' => trim((string)($row[$sexoColIdx] ?? '')),
                    'apellido_paterno' => trim((string)($row[$paternoColIdx] ?? '')),
                    'apellido_materno' => trim((string)($row[$maternoColIdx] ?? '')),
                    'nombres' => trim((string)($row[$nombresColIdx] ?? '')),
                    'apellidos_nombres' => trim(implode(' ', array_filter([
                        $row[$paternoColIdx] ?? '',
                        $row[$maternoColIdx] ?? '',
                        $row[$nombresColIdx] ?? '',
                    ]))),
                    'regimen_pensionario' => trim((string)($row[$regimenColIdx] ?? '')),
                ];
            }
        }

        return [
            'colegios' => array_values($colegiosMap),
            'estadisticas' => [
                'total_plazas' => $totalPlazas,
                'nombrados' => $nombrados,
                'contratados' => $contratados,
                'vacantes' => $vacantes,
                'designados' => $designados,
            ]
        ];
    }

    private function normalizeHeaderString(string $text): string
    {
        $text = mb_strtoupper(trim($text), 'UTF-8');
        $text = str_replace(['Á','É','Í','Ó','Ú','À','È','Ì','Ò','Ù','Ñ'], ['A','E','I','O','U','A','E','I','O','U','N'], $text);
        return preg_replace('/[^A-Z0-9]/', '', $text);
    }

    /**
     * Resuelve de forma unificada los índices de columnas tanto para REPORTE ACTUALIZADO.xls (SIRAWEB 5 colores)
     * como para reportes SIAGIE por nivel (Inicial, Primaria, Secundaria).
     */
    private function resolverColumnasMatricula(array $rows): array
    {
        $colsMap = [];
        $headerRowIdx = 5;
        $esSiagie = false;

        for ($r = 0; $r < min(15, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            foreach ($row as $cIdx => $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $norm = $this->normalizeHeaderString((string)$val);
                    if ($norm !== '' && !isset($colsMap[$norm])) {
                        $colsMap[$norm] = $cIdx;
                    }
                }
            }
            $rowStr = $this->normalizeHeaderString(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'CENTROEDUCATIVO') || str_contains($rowStr, 'CODIGOIE')) {
                $headerRowIdx = $r;
            } elseif (str_contains($rowStr, 'DRE') && (str_contains($rowStr, 'UGEL') || str_contains($rowStr, 'NOMBREDEIE') || str_contains($rowStr, 'CODMOD'))) {
                $headerRowIdx = $r;
                $esSiagie = true;
            }
        }

        // Si es formato SIAGIE, la fila inmediata siguiente puede ser subcabecera (Hombres / Mujeres)
        $dataStartIdx = $headerRowIdx + 1;
        if ($esSiagie && isset($rows[$headerRowIdx + 1])) {
            $subStr = $this->normalizeHeaderString(implode(' ', array_filter($rows[$headerRowIdx + 1], fn($v) => $v !== null)));
            if (str_contains($subStr, 'HOMBRES') || str_contains($subStr, 'MUJERES') || str_contains($subStr, 'DEFINITIVA')) {
                $dataStartIdx = $headerRowIdx + 2;
            }
        }

        if ($esSiagie && !isset($colsMap['CODIGOIE']) && !isset($colsMap['CENTROEDUCATIVO'])) {
            $codModColIdx = $colsMap['CODMOD'] ?? $colsMap['CODIGOMODULAR'] ?? 6;
            return [
                'es_siagie' => true,
                'header_row_idx' => $headerRowIdx,
                'data_start_idx' => $dataStartIdx,
                'ie_col' => $codModColIdx,
                'nombre_col' => $colsMap['NOMBREDEIE'] ?? $colsMap['INSTITUCIONEDUCATIVA'] ?? 8,
                'nivel_col' => $colsMap['NIVEL'] ?? $colsMap['NIVELEDUCATIVO'] ?? 9,
                'cod_mod_col' => $codModColIdx,
                'cod_local_col' => $colsMap['CODIGOLOCAL'] ?? $colsMap['ANEXO'] ?? 7,
                'mat_2026_col' => $colsMap['TOTALMATRICULADOS'] ?? $colsMap['TOTALDEESTUDIANTESMATRICULADOS'] ?? 12,
                'mat_2025_col' => $colsMap['MATRICULADEFINITIVA'] ?? 14,
                'secciones_col' => $colsMap['TOTALSECCIONES'] ?? 23,
                'comentario_col' => $colsMap['TIPOIE'] ?? 11,
                'plazas_col' => $colsMap['TOTALGRADOS'] ?? 22,
                'bolsa_col' => $colsMap['MATRICULAENPROCESO'] ?? 17,
                'fecha_nexus_col' => -1,
                'fecha_siagie_col' => -1,
                'fecha_escale_col' => -1,
            ];
        }

        return [
            'es_siagie' => false,
            'header_row_idx' => $headerRowIdx,
            'data_start_idx' => $headerRowIdx + 1,
            'ie_col' => $colsMap['CODIGOIE'] ?? 5, // Col F: CÓDIGO IE
            'nombre_col' => $colsMap['CENTROEDUCATIVO'] ?? $colsMap['NOMBREDEIE'] ?? 6, // Col G: CENTRO EDUCATIVO
            'nivel_col' => $colsMap['NIVELEDUCATIVO'] ?? 2, // Col C: NIVEL EDUCATIVO
            'cod_mod_col' => $colsMap['CODIGOMODULAR'] ?? 4, // Col E: CÓDIGO MODULAR
            'cod_local_col' => $colsMap['CODIGOLOCAL'] ?? 3, // Col D: CÓDIGO LOCAL
            'mat_2026_col' => $colsMap['TOTALMATRICULA2026'] ?? $colsMap['MATRICULATOTAL'] ?? 43, // Col AR
            'mat_2025_col' => $colsMap['TOTALMATRICULA2025'] ?? $colsMap['MATRICULA2025'] ?? 44, // Col AS
            'secciones_col' => $colsMap['SECCIONESNECESARIAS2025'] ?? $colsMap['SECCIONES2025'] ?? 45, // Col AT
            'comentario_col' => $colsMap['COMENTARIO'] ?? 47, // Col AV
            'plazas_col' => $colsMap['TOTALPLAZASENSSEE'] ?? $colsMap['PLAZASSEGUNSSEE'] ?? 61, // Col BJ
            'bolsa_col' => $colsMap['NRODEBHEBRSECUNDARIA'] ?? $colsMap['BOLSADEHORAS'] ?? 62, // Col BK
            'fecha_nexus_col' => $colsMap['FECHANEXUS'] ?? 63, // Col BL
            'fecha_siagie_col' => $colsMap['FECHASIAGIE'] ?? 64, // Col BM
            'fecha_escale_col' => $colsMap['FECHAPADRONESCALE'] ?? 65, // Col BN
        ];
    }

    private function extraerColegiosMatricula(array $rows): array
    {
        $cfg = $this->resolverColumnasMatricula($rows);
        $ieColIdx = $cfg['ie_col'];
        $nombreColIdx = $cfg['nombre_col'];
        $nivelColIdx = $cfg['nivel_col'];
        $codModColIdx = $cfg['cod_mod_col'];
        $codLocalColIdx = $cfg['cod_local_col'];
        $mat2026ColIdx = $cfg['mat_2026_col'];
        $mat2025ColIdx = $cfg['mat_2025_col'];
        $seccionesColIdx = $cfg['secciones_col'];
        $comentarioColIdx = $cfg['comentario_col'];
        $plazasColIdx = $cfg['plazas_col'];
        $bolsaColIdx = $cfg['bolsa_col'];
        $fechaNexusColIdx = $cfg['fecha_nexus_col'];
        $fechaSiagieColIdx = $cfg['fecha_siagie_col'];
        $fechaEscaleColIdx = $cfg['fecha_escale_col'];

        $colegiosMap = [];

        $rowCount = count($rows);
        for ($i = $cfg['data_start_idx']; $i < $rowCount; $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));
            $codIe = trim((string)($row[$ieColIdx] ?? ''));
            $nombreIe = trim((string)($row[$nombreColIdx] ?? ''));
            $nivel = trim((string)($row[$nivelColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));

            if ($codIe === '' && $nombreIe === '' && $codLocal === '') {
                continue;
            }

            $isSinRegistro = ($codIe === '' || strtoupper($codIe) === 'SIN REGISTRO');
            $key = !$isSinRegistro ? $codIe : ($codLocal !== '' ? "LOCAL_{$codLocal}" : ($codMod !== '' ? "MOD_{$codMod}" : "NOM_{$nombreIe}"));

            if (!isset($colegiosMap[$key])) {
                $colegiosMap[$key] = [
                    'key' => $key,
                    'codigo_local' => $codLocal,
                    'codigo_ie' => !$isSinRegistro ? $codIe : '',
                    'nombre' => $nombreIe,
                    'codmod' => $codMod,
                    'niveles' => [],
                    'total_filas' => 0,
                    'total_matricula_2026' => 0,
                    'total_matricula_2025' => 0,
                    'total_secciones' => 0,
                    'total_plazas' => 0,
                    'filas_detalle' => [],
                ];
            }

            if ($nombreIe !== '' && ($colegiosMap[$key]['nombre'] === '' || strlen($nombreIe) > strlen($colegiosMap[$key]['nombre']))) {
                $colegiosMap[$key]['nombre'] = $nombreIe;
            }

            if ($nivel !== '' && !in_array($nivel, $colegiosMap[$key]['niveles'], true)) {
                $colegiosMap[$key]['niveles'][] = $nivel;
            }
            $colegiosMap[$key]['total_filas']++;

            $mat26 = is_numeric($row[$mat2026ColIdx] ?? null) ? (int)$row[$mat2026ColIdx] : 0;
            $mat25 = is_numeric($row[$mat2025ColIdx] ?? null) ? (int)$row[$mat2025ColIdx] : 0;
            $sec = is_numeric($row[$seccionesColIdx] ?? null) ? (int)$row[$seccionesColIdx] : 0;
            $plz = is_numeric($row[$plazasColIdx] ?? null) ? (int)$row[$plazasColIdx] : 0;
            $bolsa = is_numeric($row[$bolsaColIdx] ?? null) ? (int)$row[$bolsaColIdx] : 0;
            $comentario = trim((string)($row[$comentarioColIdx] ?? ''));
            $fechaNexus = trim((string)($row[$fechaNexusColIdx] ?? ''));
            $fechaSiagie = trim((string)($row[$fechaSiagieColIdx] ?? ''));
            $fechaEscale = trim((string)($row[$fechaEscaleColIdx] ?? ''));

            $colegiosMap[$key]['total_matricula_2026'] += $mat26;
            $colegiosMap[$key]['total_matricula_2025'] += $mat25;
            $colegiosMap[$key]['total_secciones'] += $sec;
            $colegiosMap[$key]['total_plazas'] += $plz;

            $colegiosMap[$key]['filas_detalle'][] = [
                'fila_origen' => $i + 1,
                'nivel' => $nivel ?: 'REGULAR',
                'cod_mod' => $codMod,
                'cod_local' => $codLocal,
                'cod_ie' => $codIe,
                'nombre' => $nombreIe,
                'matricula_2026' => $mat26,
                'matricula_2025' => $mat25,
                'secciones' => $sec,
                'comentario' => $comentario,
                'plazas' => $plz,
                'bolsa_horas' => $bolsa,
                'fecha_nexus' => $fechaNexus,
                'fecha_siagie' => $fechaSiagie,
                'fecha_escale' => $fechaEscale,
            ];
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

        $outSheet->setCellValue('A1', 'GOBIERNO REGIONAL DE PIURA - UNIDAD DE GESTION EDUCATIVA LOCAL PIURA');
        $outSheet->setCellValue('A2', 'CUADRO DE PLAZAS NEXUS - I.E. ' . mb_strtoupper($nombreColegio, 'UTF-8'));
        $outSheet->setCellValue('A3', '');

        $bannerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $outSheet->getStyle("A1:{$lastColLetter}3")->applyFromArray($bannerStyle);
        $outSheet->getRowDimension(1)->setRowHeight(24);
        $outSheet->getRowDimension(2)->setRowHeight(24);
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
        $outSheet->getRowDimension(4)->setRowHeight(34);

        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;
        $dniColIdx = $colsMap['DOCUMENTO DE IDENTIDAD'] ?? 35;

        // Datos Fila 5 en adelante
        $currentRow = 5;
        foreach ($filteredRows as $rData) {
            $rowSituacion = strtoupper(trim((string)($rData[$situacionColIdx] ?? '')));
            $isRowVacante = str_contains($rowSituacion, 'VACANTE');

            for ($col = 1; $col <= count($rData); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = $rData[$col - 1] ?? '';
                $cIdx = $col - 1;
                $headerName = strtoupper(trim((string)($headers[$cIdx] ?? '')));

                if (str_contains($headerName, 'FECHA') && is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
                    try {
                        $val = Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                    } catch (\Throwable $e) {}
                }

                if ($cIdx === $dniColIdx && $isRowVacante && (trim((string)$val) === '' || trim((string)$val) === '0')) {
                    $val = 'VACANTE';
                }

                $outSheet->setCellValue("{$colLetter}{$currentRow}", $val);

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

                if ($cIdx === $dniColIdx && (strtoupper(trim((string)$val)) === 'VACANTE' || $isRowVacante)) {
                    $outSheet->getStyle("{$colLetter}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8D7DA']],
                        'font' => ['bold' => true, 'color' => ['rgb' => '721C24']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }

            $outSheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $outSheet->getStyle("A{$currentRow}:E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("F{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("L{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("O{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("P{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $outSheet->getStyle("Q{$currentRow}:S{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("U{$currentRow}:W{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $outSheet->getStyle("X{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("Y{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $outSheet->getStyle("Z{$currentRow}:AD{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("AE{$currentRow}:AF{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("AJ{$currentRow}:AK{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("AL{$currentRow}:AM{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("AN{$currentRow}:AP{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $outSheet->getStyle("AX{$currentRow}:BB{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $outSheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        foreach (self::NEXUS_OFFICIAL_WIDTHS as $colL => $w) {
            $outSheet->getColumnDimension($colL)->setWidth($w);
        }

        $outputDir = $this->getWritableDir('nexus_output');
        $safeName = preg_replace('/[^\w\s\.-]/u', '_', $nombreColegio);
        $safeName = preg_replace('/\s+/', ' ', trim($safeName));
        if ($safeName === '') {
            $safeName = 'IE';
        }
        $filename = "NEXUS - {$safeName}.xlsx";
        $fullPath = "{$outputDir}/" . uniqid('nx_', true) . "_{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($fullPath);

        $outSpreadsheet->disconnectWorksheets();
        unset($outSheet, $outSpreadsheet, $writer);

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
        $rows = $this->leerFilasExcelOptimizado($filePath);

        $headerRowIdx = -1;
        for ($idx = 0; $idx < min(12, count($rows)); $idx++) {
            $rowStr = strtoupper(implode(' ', array_filter($rows[$idx] ?? [], fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) {
            $headerRowIdx = 4;
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
        $codLocalColIdx = $colsMap['CODIGO LOCAL'] ?? 11;

        $filteredRows = [];
        $nombreEncontrado = '';
        $cleanTarget = trim($nombreColegio);

        $rowCount = count($rows);
        for ($i = $headerRowIdx + 1; $i < $rowCount; $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));

            if (
                strcasecmp($nombreIe, $cleanTarget) === 0 ||
                strcasecmp($codMod, $cleanTarget) === 0 ||
                strcasecmp($codLocal, $cleanTarget) === 0 ||
                strcasecmp("LOCAL_{$codLocal}", $cleanTarget) === 0 ||
                strcasecmp("MOD_{$codMod}", $cleanTarget) === 0 ||
                (strlen($cleanTarget) >= 4 && str_contains(strtoupper($nombreIe), strtoupper($cleanTarget)))
            ) {
                $filteredRows[] = $row;
                if ($nombreEncontrado === '' || strlen($nombreIe) > strlen($nombreEncontrado)) {
                    $nombreEncontrado = $nombreIe;
                }
            }
        }

        unset($rows);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $nombreFinal = $nombreEncontrado ?: $cleanTarget;
        return $this->generarExcelNexusColegioDesdeFilas($nombreFinal, $filteredRows, $headers, $colsMap);
    }

    /**
     * Construye y guarda el archivo Excel REPORTE MATRÍCULA para un colegio a partir de sus filas filtradas.
     * Soporta recibir un arreglo de filas ($sourceDataRows) o una hoja $sourceSheet.
     */
    public function generarExcelMatriculaColegioDesdeFilas(string $nombreColegio, string $codIe, array $matchingRowIndices, $sourceSheetOrRows): array
    {
        $headerRowIdx = 6;
        $isArraySource = is_array($sourceSheetOrRows);

        if ($isArraySource) {
            $maxCols = 66;
            for ($r = 0; $r < min(10, count($sourceSheetOrRows)); $r++) {
                if (!empty($sourceSheetOrRows[$r])) {
                    $maxCols = max($maxCols, count($sourceSheetOrRows[$r]));
                }
            }
            $highestColNum = $maxCols;
        } else {
            $highestColNum = Coordinate::columnIndexFromString($sourceSheetOrRows->getHighestColumn());
        }

        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();

        $cleanName = preg_replace('/[\\\\\/?*\[\]:]/', '', $nombreColegio ?: $codIe);
        $safeTitle = substr('REPORTE - ' . ($cleanName ?: 'IE'), 0, 30);
        $outSheet->setTitle($safeTitle);

        // 1. Aplicar anchos de columna oficiales
        for ($c = 1; $c <= $highestColNum; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            if (!$isArraySource && $sourceSheetOrRows->getColumnDimension($colLetter)->getWidth() > 0) {
                $outSheet->getColumnDimension($colLetter)->setWidth($sourceSheetOrRows->getColumnDimension($colLetter)->getWidth());
            } elseif (isset(self::MATRICULA_OFFICIAL_WIDTHS[$c])) {
                $outSheet->getColumnDimension($colLetter)->setWidth(self::MATRICULA_OFFICIAL_WIDTHS[$c]);
            } else {
                $outSheet->getColumnDimension($colLetter)->setWidth(11.5);
            }
        }

        // 2. Aplicar celdas combinadas de la cabecera (filas 1 a 6)
        $appliedMerges = false;
        if (!$isArraySource) {
            foreach ($sourceSheetOrRows->getMergeCells() as $mergeRange) {
                if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $mergeRange, $m)) {
                    $endRow = (int)$m[4];
                    if ($endRow <= $headerRowIdx) {
                        $outSheet->mergeCells($mergeRange);
                        $appliedMerges = true;
                    }
                }
            }
        }
        if (!$appliedMerges) {
            foreach (self::MATRICULA_OFFICIAL_MERGES as $mergeRange) {
                try {
                    $outSheet->mergeCells($mergeRange);
                } catch (\Throwable $e) {}
            }
        }

        // 3. Copiar contenido de las filas de cabecera (Filas 1 a 6)
        for ($r = 1; $r <= $headerRowIdx; $r++) {
            if ($isArraySource) {
                $rowArr = $sourceSheetOrRows[$r - 1] ?? [];
                for ($c = 1; $c <= $highestColNum; $c++) {
                    $val = $rowArr[$c - 1] ?? null;
                    if ($val !== null && $val !== '') {
                        $outSheet->setCellValue([$c, $r], $val);
                    }
                }
            } else {
                $rowDim = $sourceSheetOrRows->getRowDimension($r);
                if ($rowDim->getRowHeight() > 0) {
                    $outSheet->getRowDimension($r)->setRowHeight($rowDim->getRowHeight());
                }
                for ($c = 1; $c <= $highestColNum; $c++) {
                    $val = $sourceSheetOrRows->getCell([$c, $r])->getValue();
                    if ($val !== null && $val !== '') {
                        $outSheet->setCellValue([$c, $r], $val);
                    }
                }
            }
        }

        // 4. Copiar filas de datos filtradas (a partir de la fila 7 en adelante)
        $outRow = $headerRowIdx + 1;
        foreach ($matchingRowIndices as $srcRowOrData) {
            $outSheet->getRowDimension($outRow)->setRowHeight(20);

            if (is_array($srcRowOrData)) {
                for ($c = 1; $c <= $highestColNum; $c++) {
                    $val = $srcRowOrData[$c - 1] ?? null;
                    if ($val !== null && $val !== '') {
                        $outSheet->setCellValue([$c, $outRow], $val);
                    }
                }
            } elseif ($isArraySource) {
                // $srcRowOrData es índice 0-based o 1-based en $sourceSheetOrRows
                $rowArr = $sourceSheetOrRows[$srcRowOrData] ?? ($sourceSheetOrRows[$srcRowOrData - 1] ?? []);
                for ($c = 1; $c <= $highestColNum; $c++) {
                    $val = $rowArr[$c - 1] ?? null;
                    if ($val !== null && $val !== '') {
                        $outSheet->setCellValue([$c, $outRow], $val);
                    }
                }
            } else {
                $srcRow = (int)$srcRowOrData;
                for ($c = 1; $c <= $highestColNum; $c++) {
                    $val = $sourceSheetOrRows->getCell([$c, $srcRow])->getValue();
                    if ($val !== null && $val !== '') {
                        $outSheet->setCellValue([$c, $outRow], $val);
                    }
                }
            }
            $outRow++;
        }

        // 5. Aplicar la paleta de colores oficiales y bordes idéntica a las capturas (5 secciones coloreadas)
        $this->aplicarEstilosOficialesMatricula($outSpreadsheet, $headerRowIdx + 1, $outRow - 1);

        // Guardar archivo individual con nombre único en disco para evitar colisiones concurrentes
        $outputDir = $this->getWritableDir('matricula_colegios');
        $safeName = preg_replace('/[^\w\s\.-]/u', '_', $nombreColegio ?: $codIe);
        $safeName = preg_replace('/\s+/', ' ', trim($safeName));
        if ($safeName === '') {
            $safeName = $codIe ?: 'IE';
        }
        $filename = "REPORTE - I.E. {$safeName}.xlsx";
        $fullPath = "{$outputDir}/" . uniqid('mat_', true) . "_{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($fullPath);

        $outSpreadsheet->disconnectWorksheets();
        unset($outSheet, $outSpreadsheet, $writer);

        return [
            'filename' => $filename,
            'path' => $fullPath,
            'registros' => count($matchingRowIndices),
        ];
    }

    /**
     * Exportación de Excel REPORTE MATRÍCULA individual filtrando prioritariamente por CÓDIGO IE (Columna F).
     */
    public function exportarMatriculaColegio($fileSource, string $codigoColegio): array
    {
        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();
        $rows = $this->leerFilasExcelOptimizado($filePath);

        $cfg = $this->resolverColumnasMatricula($rows);
        $headerRowIdx = $cfg['data_start_idx'] - 1;
        $ieCol = $cfg['ie_col'];
        $nombreCol = $cfg['nombre_col'];
        $codModCol = $cfg['cod_mod_col'];
        $codLocalCol = $cfg['cod_local_col'];

        $matchingRows = [];
        $nombreEncontrado = '';
        $codIeEncontrado = '';
        $rowCount = count($rows);

        for ($r = $cfg['data_start_idx']; $r < $rowCount; $r++) {
            $row = $rows[$r] ?? [];
            $codIeVal = trim((string)($row[$ieCol] ?? ''));
            $nombreIeVal = trim((string)($row[$nombreCol] ?? ''));
            $codModVal = trim((string)($row[$codModCol] ?? ''));
            $codLocalVal = trim((string)($row[$codLocalCol] ?? ''));

            if ($codIeVal === '' && $nombreIeVal === '' && $codModVal === '') {
                continue;
            }

            $esMatch = false;
            if ($codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO' && strcasecmp($codIeVal, $codigoColegio) === 0) {
                $esMatch = true;
            } elseif (
                strcasecmp($codLocalVal, $codigoColegio) === 0 ||
                strcasecmp("LOCAL_{$codLocalVal}", $codigoColegio) === 0 ||
                strcasecmp($codModVal, $codigoColegio) === 0 ||
                strcasecmp("MOD_{$codModVal}", $codigoColegio) === 0 ||
                strcasecmp($nombreIeVal, $codigoColegio) === 0 ||
                strcasecmp("NOM_{$nombreIeVal}", $codigoColegio) === 0
            ) {
                $esMatch = true;
            }

            if ($esMatch) {
                $matchingRows[] = $row;
                if ($nombreEncontrado === '' || strlen($nombreIeVal) > strlen($nombreEncontrado)) {
                    $nombreEncontrado = $nombreIeVal;
                }
                if ($codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO') {
                    $codIeEncontrado = $codIeVal;
                }
            }
        }

        // Búsqueda parcial si no hubo coincidencia exacta
        if (empty($matchingRows)) {
            for ($r = $headerRowIdx + 1; $r < $rowCount; $r++) {
                $row = $rows[$r] ?? [];
                $codIeVal = trim((string)($row[$ieCol] ?? ''));
                $nombreIeVal = trim((string)($row[$nombreCol] ?? ''));

                if (
                    ($codIeVal !== '' && str_contains(strtoupper($codIeVal), strtoupper($codigoColegio))) ||
                    ($nombreIeVal !== '' && str_contains(strtoupper($nombreIeVal), strtoupper($codigoColegio)))
                ) {
                    $matchingRows[] = $row;
                    if ($nombreEncontrado === '') {
                        $nombreEncontrado = $nombreIeVal;
                    }
                    if ($codIeEncontrado === '' && $codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO') {
                        $codIeEncontrado = $codIeVal;
                    }
                }
            }
        }

        $headerRowsOnly = array_slice($rows, 0, $headerRowIdx + 1);
        unset($rows);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return $this->generarExcelMatriculaColegioDesdeFilas(
            $nombreEncontrado ?: $codigoColegio,
            $codIeEncontrado ?: $codigoColegio,
            $matchingRows,
            $headerRowsOnly
        );
    }

    /**
     * Empaqueta entradas OpenXML pre-comprimidas y dinámicas en un binario .xlsx válido en memoria,
     * evitando crear cientos de objetos PhpSpreadsheet ni archivos temporales en disco.
     */
    private function buildXlsxBinaryInMemory(array $preCompressedEntries, array $dynamicEntries): string
    {
        $localParts = [];
        $centralParts = [];
        $offset = 0;
        $count = 0;

        $all = $preCompressedEntries;
        foreach ($dynamicEntries as $name => $raw) {
            $comp = gzdeflate($raw, 6);
            $all[$name] = [
                'name' => $name,
                'crc' => crc32($raw),
                'uncompressed_size' => strlen($raw),
                'compressed_size' => strlen($comp),
                'compressed_data' => $comp,
            ];
        }

        foreach ($all as $entry) {
            $name = $entry['name'];
            $nameLen = strlen($name);
            $crc = $entry['crc'];
            $uSize = $entry['uncompressed_size'];
            $cSize = $entry['compressed_size'];
            $cData = $entry['compressed_data'];

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0x2100, $crc, $cSize, $uSize, $nameLen, 0) . $name;
            $localParts[] = $localHeader . $cData;

            $centralParts[] = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0x2100, $crc, $cSize, $uSize, $nameLen, 0, 0, 0, 0, 32, $offset) . $name;

            $offset += strlen($localHeader) + $cSize;
            $count++;
        }

        $centralDir = implode('', $centralParts);
        $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($centralDir), $offset, 0);

        return implode('', $localParts) . $centralDir . $eocd;
    }

    /**
     * Limpia caracteres de control inválidos en XML 1.0 antes de escapar con htmlspecialchars.
     */
    private function escapeXmlValue(string $val): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val) ?? $val;
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Exportación Masiva en archivo ZIP de todos los colegios procesados.
     * Utiliza generación OpenXML en memoria a partir de una única plantilla oficial para:
     * 1) Evitar el error "ZipArchive::close(): Unexpected length of data" por colisión de nombres en disco.
     * 2) Reducir el uso de RAM en Render de >500 MB a <35 MB y el tiempo de ejecución de >60s a <2s.
     */
    public function exportarZipColegios($fileSource): array
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '384M');

        $filePath = is_string($fileSource) ? $fileSource : $fileSource->getRealPath();

        $outputDir = $this->getWritableDir('temp_zips');
        $this->cleanTempFilesInDir($outputDir, 60);
        $this->cleanTempFilesInDir($this->getWritableDir('matricula_colegios'), 60);
        $this->cleanTempFilesInDir($this->getWritableDir('nexus_output'), 60);

        // 1. Leer filas del archivo una única vez con lector ultraligero
        $rows = $this->leerFilasExcelOptimizado($filePath);
        if (empty($rows)) {
            throw new Exception('No se encontraron datos en el archivo para exportar.');
        }

        $esNexus = $this->esArchivoNexus($rows);
        $tipoExcel = $esNexus ? 'NEXUS' : 'MATRICULA';

        if ($esNexus) {
            $zipDownloadName = 'REPORTES_NEXUS_ORGANIZADOS_POR_COLEGIO.zip';
            $basePath = 'REPORTES_NEXUS_ORGANIZADOS_POR_COLEGIO';
        } else {
            $zipDownloadName = 'REPORTES_ORGANIZADOS_POR_COLEGIO_Y_NIVEL.zip';
            $basePath = 'REPORTES_ORGANIZADOS_POR_COLEGIO_Y_NIVEL/COLEGIOS_UGEL_PIURA';
        }

        $zipPath = "{$outputDir}/" . uniqid('pkg_', true) . "_{$zipDownloadName}";

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo ZIP en el servidor.');
        }

        if ($esNexus) {
            $this->empaquetarZipNexusEnMemoria($zip, $rows, $basePath);
        } else {
            $this->empaquetarZipMatriculaEnMemoria($zip, $rows, $basePath);
        }

        unset($rows);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if ($zip->close() !== true) {
            @unlink($zipPath);
            throw new Exception('No se pudo finalizar la escritura del paquete ZIP.');
        }

        return [
            'path' => $zipPath,
            'filename' => $zipDownloadName,
        ];
    }

    /**
     * Genera todos los archivos Excel NEXUS por colegio directamente en memoria dentro del ZipArchive.
     */
    private function empaquetarZipNexusEnMemoria(\ZipArchive $zip, array &$rows, string $basePath): void
    {
        $headerRowIdx = -1;
        for ($idx = 0; $idx < min(12, count($rows)); $idx++) {
            $rowStr = strtoupper(implode(' ', array_filter($rows[$idx] ?? [], fn($v) => $v !== null)));
            if (str_contains($rowStr, 'NOMBRE DE LA REGION') || str_contains($rowStr, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                $headerRowIdx = $idx;
                break;
            }
        }
        if ($headerRowIdx === -1) {
            $headerRowIdx = 4;
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
        $codLocalColIdx = $colsMap['CODIGO LOCAL'] ?? 11;
        $situacionColIdx = $colsMap['SITUACION LABORAL'] ?? 23;
        $dniColIdx = $colsMap['DOCUMENTO DE IDENTIDAD'] ?? 35;

        // Identificar columnas de fecha para formatear números seriales de Excel
        $dateColFlags = [];
        foreach ($headers as $cIdx => $hVal) {
            $hName = strtoupper(trim((string)$hVal));
            if (str_contains($hName, 'FECHA')) {
                $dateColFlags[$cIdx] = true;
            }
        }

        // Agrupar filas por colegio en una sola pasada y liberar las filas originales conforme avanzamos
        $colegiosRowsMap = [];
        $rowCount = count($rows);
        for ($i = $headerRowIdx + 1; $i < $rowCount; $i++) {
            $row = $rows[$i];
            unset($rows[$i]); // Liberar memoria progresivamente
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                continue;
            }

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));

            $groupKey = $nombreIe !== '' ? $nombreIe : ($codMod !== '' ? "IE_{$codMod}" : 'COLEGIO_SIN_NOMBRE');
            $colegiosRowsMap[$groupKey][] = $row;
        }

        if (empty($colegiosRowsMap)) {
            throw new Exception('No se encontraron colegios en el archivo NEXUS para exportar.');
        }

        // Construir 1 plantilla maestra con las 5 variantes de fila (Normal, Nombrado, Contratado, Vacante, Designado)
        $totalCols = max(66, count($headers));
        $sampleRows = [];
        foreach (['OTRO', 'NOMBRADO', 'CONTRATADO', 'VACANTE', 'DESIGNADO'] as $sitType) {
            $sRow = array_fill(0, $totalCols, 'X');
            $sRow[$situacionColIdx] = $sitType;
            if ($sitType === 'VACANTE') {
                $sRow[$dniColIdx] = 'VACANTE';
            }
            $sampleRows[] = $sRow;
        }

        $tplRes = $this->generarExcelNexusColegioDesdeFilas('__TPL_SCHOOL__', $sampleRows, $headers, $colsMap);
        $tplPath = $tplRes['path'];

        $tplZip = new \ZipArchive();
        if ($tplZip->open($tplPath) !== true) {
            @unlink($tplPath);
            throw new Exception('No se pudo inicializar la plantilla de exportación NEXUS.');
        }

        $preCompressed = [];
        $sheet1Xml = '';
        $workbookXml = '';
        for ($i = 0; $i < $tplZip->numFiles; $i++) {
            $name = $tplZip->getNameIndex($i);
            $raw = $tplZip->getFromIndex($i);
            if ($name === 'xl/worksheets/sheet1.xml') {
                $sheet1Xml = $raw;
            } elseif ($name === 'xl/workbook.xml') {
                $workbookXml = $raw;
            } else {
                $comp = gzdeflate($raw, 6);
                $preCompressed[$name] = [
                    'name' => $name,
                    'crc' => crc32($raw),
                    'uncompressed_size' => strlen($raw),
                    'compressed_size' => strlen($comp),
                    'compressed_data' => $comp,
                ];
            }
        }
        $tplZip->close();
        @unlink($tplPath);

        // Extraer los índices de estilo s="..." de las filas 5..9 de la plantilla
        $colLetters = [];
        for ($c = 1; $c <= $totalCols; $c++) {
            $colLetters[$c - 1] = Coordinate::stringFromColumnIndex($c);
        }
        $sitColLetter = $colLetters[$situacionColIdx] ?? 'X';
        $dniColLetter = $colLetters[$dniColIdx] ?? 'AJ';

        $baseColStyles = [];
        $styleSitNombrado = '';
        $styleSitContratado = '';
        $styleSitVacante = '';
        $styleSitDesignado = '';
        $styleDniVacante = '';
        $styleBannerA2 = '1';

        $sxe = new \SimpleXMLElement($sheet1Xml);
        foreach ($sxe->sheetData->row as $rNode) {
            $rNum = (int)$rNode['r'];
            if ($rNum === 2) {
                foreach ($rNode->c as $cNode) {
                    if ((string)$cNode['r'] === 'A2') {
                        $styleBannerA2 = (string)$cNode['s'];
                    }
                }
            } elseif ($rNum === 5) {
                foreach ($rNode->c as $cNode) {
                    $colL = preg_replace('/\d+/', '', (string)$cNode['r']);
                    $baseColStyles[$colL] = (string)$cNode['s'];
                }
            } elseif ($rNum === 6) {
                foreach ($rNode->c as $cNode) {
                    if ((string)$cNode['r'] === "{$sitColLetter}6") {
                        $styleSitNombrado = (string)$cNode['s'];
                    }
                }
            } elseif ($rNum === 7) {
                foreach ($rNode->c as $cNode) {
                    if ((string)$cNode['r'] === "{$sitColLetter}7") {
                        $styleSitContratado = (string)$cNode['s'];
                    }
                }
            } elseif ($rNum === 8) {
                foreach ($rNode->c as $cNode) {
                    if ((string)$cNode['r'] === "{$sitColLetter}8") {
                        $styleSitVacante = (string)$cNode['s'];
                    } elseif ((string)$cNode['r'] === "{$dniColLetter}8") {
                        $styleDniVacante = (string)$cNode['s'];
                    }
                }
            } elseif ($rNum === 9) {
                foreach ($rNode->c as $cNode) {
                    if ((string)$cNode['r'] === "{$sitColLetter}9") {
                        $styleSitDesignado = (string)$cNode['s'];
                    }
                }
            }
        }
        unset($sxe);

        $posRow5Start = strpos($sheet1Xml, '<row r="5"');
        $posSheetDataEnd = strpos($sheet1Xml, '</sheetData>');
        $xmlPrefix = substr($sheet1Xml, 0, $posRow5Start);
        $xmlSuffix = substr($sheet1Xml, $posSheetDataEnd);

        // Reemplazar celda A2 en $xmlPrefix por un placeholder para inyectar el nombre de cada IE como inlineStr
        $xmlPrefix = preg_replace(
            '/<c r="A2"[^>]*>.*?<\/c>/s',
            '<c r="A2" s="' . $styleBannerA2 . '" t="inlineStr"><is><t>__BANNER_A2_TEXT__</t></is></c>',
            $xmlPrefix,
            1
        );

        $usedZipPaths = [];

        foreach ($colegiosRowsMap as $nombreColegio => $schoolRows) {
            unset($colegiosRowsMap[$nombreColegio]); // Liberar conforme procesamos

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
            if ($folderNameSafe === '') {
                $folderNameSafe = 'COLEGIO';
            }

            $safeName = preg_replace('/[^\w\s\.-]/u', '_', (string)$nombreColegio);
            $safeName = preg_replace('/\s+/', ' ', trim($safeName));
            if ($safeName === '') {
                $safeName = 'IE';
            }
            $filename = "NEXUS - {$safeName}.xlsx";

            $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$filename}";
            if (isset($usedZipPaths[$zipInternalPath])) {
                $usedZipPaths[$zipInternalPath]++;
                $zipInternalPath = "{$basePath}/{$folderNameSafe}_{$usedZipPaths[$zipInternalPath]}/{$filename}";
            } else {
                $usedZipPaths[$zipInternalPath] = 1;
            }

            // Construir filas XML de datos (Fila 5 en adelante)
            $rowsXml = '';
            $currentRow = 5;
            foreach ($schoolRows as $rData) {
                $rowSituacion = strtoupper(trim((string)($rData[$situacionColIdx] ?? '')));
                $isRowVacante = str_contains($rowSituacion, 'VACANTE');

                $rowsXml .= '<row r="' . $currentRow . '" ht="20" customHeight="1">';
                $colMax = max($totalCols, count($rData));
                for ($cIdx = 0; $cIdx < $colMax; $cIdx++) {
                    $colL = $colLetters[$cIdx] ?? Coordinate::stringFromColumnIndex($cIdx + 1);
                    $val = $rData[$cIdx] ?? '';

                    if (isset($dateColFlags[$cIdx]) && is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
                        try {
                            $val = Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                        } catch (\Throwable $e) {}
                    }

                    if ($cIdx === $dniColIdx && $isRowVacante && (trim((string)$val) === '' || trim((string)$val) === '0')) {
                        $val = 'VACANTE';
                    }

                    $styleId = $baseColStyles[$colL] ?? '';
                    if ($cIdx === $situacionColIdx) {
                        if (str_contains($rowSituacion, 'NOMBRADO') && $styleSitNombrado !== '') {
                            $styleId = $styleSitNombrado;
                        } elseif (str_contains($rowSituacion, 'CONTRATADO') && $styleSitContratado !== '') {
                            $styleId = $styleSitContratado;
                        } elseif ($isRowVacante && $styleSitVacante !== '') {
                            $styleId = $styleSitVacante;
                        } elseif (str_contains($rowSituacion, 'DESIGNADO') && $styleSitDesignado !== '') {
                            $styleId = $styleSitDesignado;
                        }
                    } elseif ($cIdx === $dniColIdx && ($isRowVacante || strtoupper(trim((string)$val)) === 'VACANTE') && $styleDniVacante !== '') {
                        $styleId = $styleDniVacante;
                    }

                    $sAttr = $styleId !== '' ? ' s="' . $styleId . '"' : '';
                    $strVal = (string)$val;

                    if ($strVal === '') {
                        $rowsXml .= '<c r="' . $colL . $currentRow . '"' . $sAttr . '/>';
                    } elseif (is_numeric($strVal) && !preg_match('/^0\d+$/', $strVal)) {
                        $rowsXml .= '<c r="' . $colL . $currentRow . '"' . $sAttr . '><v>' . $strVal . '</v></c>';
                    } else {
                        $rowsXml .= '<c r="' . $colL . $currentRow . '"' . $sAttr . ' t="inlineStr"><is><t>' . $this->escapeXmlValue($strVal) . '</t></is></c>';
                    }
                }
                $rowsXml .= '</row>';
                $currentRow++;
            }

            $bannerTitle = $this->escapeXmlValue('CUADRO DE PLAZAS NEXUS - I.E. ' . mb_strtoupper((string)$nombreColegio, 'UTF-8'));
            $schoolPrefix = str_replace('__BANNER_A2_TEXT__', $bannerTitle, $xmlPrefix);
            $schoolSheetXml = $schoolPrefix . $rowsXml . $xmlSuffix;

            $safeSheetTitle = 'NEXUS - ' . substr(preg_replace('/[\\\\\/?*\[\]:]/', '', (string)$nombreColegio), 0, 20);
            $schoolWorkbookXml = str_replace(
                'name="NEXUS - __TPL_SCHOOL"',
                'name="' . $this->escapeXmlValue($safeSheetTitle) . '"',
                $workbookXml
            );

            $xlsxBin = $this->buildXlsxBinaryInMemory($preCompressed, [
                'xl/workbook.xml' => $schoolWorkbookXml,
                'xl/worksheets/sheet1.xml' => $schoolSheetXml,
            ]);

            $zip->addFromString($zipInternalPath, $xlsxBin);
            $zip->setCompressionName($zipInternalPath, \ZipArchive::CM_STORE);
        }
    }

    /**
     * Genera todos los archivos Excel REPORTE MATRÍCULA por colegio directamente en memoria dentro del ZipArchive.
     */
    private function empaquetarZipMatriculaEnMemoria(\ZipArchive $zip, array &$rows, string $basePath): void
    {
        $cfg = $this->resolverColumnasMatricula($rows);
        $headerRowIdx = $cfg['data_start_idx'] - 1;
        $ieCol = $cfg['ie_col'];
        $nombreCol = $cfg['nombre_col'];
        $codModCol = $cfg['cod_mod_col'];
        $codLocalCol = $cfg['cod_local_col'];

        $headerRowsOnly = array_slice($rows, 0, $headerRowIdx + 1);
        $highestColNum = 66;
        foreach ($headerRowsOnly as $hRow) {
            if (is_array($hRow)) {
                $highestColNum = max($highestColNum, count($hRow));
            }
        }

        // Agrupar filas por colegio (CÓDIGO IE como clave principal) y liberar $rows progresivamente
        $rowsPerSchool = [];
        $schoolMeta = [];
        $rowCount = count($rows);

        for ($r = $cfg['data_start_idx']; $r < $rowCount; $r++) {
            $row = $rows[$r];
            unset($rows[$r]);
            if (!is_array($row)) {
                continue;
            }

            $cIe = trim((string)($row[$ieCol] ?? ''));
            $cLocal = trim((string)($row[$codLocalCol] ?? ''));
            $cMod = trim((string)($row[$codModCol] ?? ''));
            $cNom = trim((string)($row[$nombreCol] ?? ''));

            if ($cIe === '' && $cNom === '' && $cLocal === '') {
                continue;
            }

            $isSinReg = ($cIe === '' || strtoupper($cIe) === 'SIN REGISTRO');
            $k = !$isSinReg ? $cIe : ($cLocal !== '' ? "LOCAL_{$cLocal}" : ($cMod !== '' ? "MOD_{$cMod}" : "NOM_{$cNom}"));

            $rowsPerSchool[$k][] = $row;

            if (!isset($schoolMeta[$k])) {
                $schoolMeta[$k] = [
                    'codigo_ie' => !$isSinReg ? $cIe : '',
                    'codigo_local' => $cLocal,
                    'nombre' => $cNom,
                    'codmod' => $cMod,
                ];
            } elseif ($cNom !== '' && strlen($cNom) > strlen($schoolMeta[$k]['nombre'])) {
                $schoolMeta[$k]['nombre'] = $cNom;
            }
        }

        if (empty($schoolMeta)) {
            throw new Exception('No se encontraron colegios en el Reporte de Matrícula para exportar.');
        }

        // Construir 1 plantilla maestra con las filas de cabecera 1..6 y 1 fila de muestra en fila 7
        $sampleRow = array_fill(0, $highestColNum, 'X');
        $tplRes = $this->generarExcelMatriculaColegioDesdeFilas('__TPL_MAT__', '000000', [$sampleRow], $headerRowsOnly);
        $tplPath = $tplRes['path'];

        $tplZip = new \ZipArchive();
        if ($tplZip->open($tplPath) !== true) {
            @unlink($tplPath);
            throw new Exception('No se pudo inicializar la plantilla de exportación de Matrícula.');
        }

        $preCompressed = [];
        $sheet1Xml = '';
        $workbookXml = '';
        for ($i = 0; $i < $tplZip->numFiles; $i++) {
            $name = $tplZip->getNameIndex($i);
            $raw = $tplZip->getFromIndex($i);
            if ($name === 'xl/worksheets/sheet1.xml') {
                $sheet1Xml = $raw;
            } elseif ($name === 'xl/workbook.xml') {
                $workbookXml = $raw;
            } else {
                $comp = gzdeflate($raw, 6);
                $preCompressed[$name] = [
                    'name' => $name,
                    'crc' => crc32($raw),
                    'uncompressed_size' => strlen($raw),
                    'compressed_size' => strlen($comp),
                    'compressed_data' => $comp,
                ];
            }
        }
        $tplZip->close();
        @unlink($tplPath);

        $colLetters = [];
        for ($c = 1; $c <= $highestColNum; $c++) {
            $colLetters[$c - 1] = Coordinate::stringFromColumnIndex($c);
        }

        $dataRowNumber = $headerRowIdx + 2; // Fila 7 (1-based)
        $colStyles = [];
        $sxe = new \SimpleXMLElement($sheet1Xml);
        foreach ($sxe->sheetData->row as $rNode) {
            if ((int)$rNode['r'] === $dataRowNumber) {
                foreach ($rNode->c as $cNode) {
                    $colL = preg_replace('/\d+/', '', (string)$cNode['r']);
                    $colStyles[$colL] = (string)$cNode['s'];
                }
            }
        }
        unset($sxe);

        $posDataRowStart = strpos($sheet1Xml, '<row r="' . $dataRowNumber . '"');
        $posSheetDataEnd = strpos($sheet1Xml, '</sheetData>');
        $xmlPrefix = substr($sheet1Xml, 0, $posDataRowStart);
        $xmlSuffix = substr($sheet1Xml, $posSheetDataEnd);

        $usedZipPaths = [];

        foreach ($schoolMeta as $k => $meta) {
            $schoolRows = $rowsPerSchool[$k] ?? [];
            unset($rowsPerSchool[$k], $schoolMeta[$k]);
            if (empty($schoolRows)) {
                continue;
            }

            $codCode = !empty($meta['codigo_local'])
                ? trim((string)$meta['codigo_local'])
                : (!empty($meta['codigo_ie']) ? trim((string)$meta['codigo_ie']) : '000000');
            if (strlen($codCode) < 6 && is_numeric($codCode)) {
                $codCode = str_pad($codCode, 6, '0', STR_PAD_LEFT);
            }
            $nombreIe = trim((string)($meta['nombre'] ?? 'IE'));
            if ($nombreIe === '') {
                $nombreIe = "IE_{$codCode}";
            }

            $folderName = "{$codCode} - {$nombreIe}";
            $folderNameSafe = preg_replace('/[^\w\s\.-]/u', '_', $folderName);
            $folderNameSafe = preg_replace('/\s+/', ' ', trim($folderNameSafe));
            if ($folderNameSafe === '') {
                $folderNameSafe = $codCode;
            }

            $safeName = preg_replace('/[^\w\s\.-]/u', '_', $nombreIe ?: ($meta['codigo_ie'] ?: 'IE'));
            $safeName = preg_replace('/\s+/', ' ', trim($safeName));
            if ($safeName === '') {
                $safeName = $codCode;
            }
            $filename = "REPORTE - I.E. {$safeName}.xlsx";

            $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$filename}";
            if (isset($usedZipPaths[$zipInternalPath])) {
                $usedZipPaths[$zipInternalPath]++;
                $zipInternalPath = "{$basePath}/{$folderNameSafe}_{$usedZipPaths[$zipInternalPath]}/{$filename}";
            } else {
                $usedZipPaths[$zipInternalPath] = 1;
            }

            $rowsXml = '';
            $outRow = $dataRowNumber;
            foreach ($schoolRows as $rData) {
                $rowsXml .= '<row r="' . $outRow . '" ht="20" customHeight="1">';
                for ($cIdx = 0; $cIdx < $highestColNum; $cIdx++) {
                    $colL = $colLetters[$cIdx];
                    $styleId = $colStyles[$colL] ?? '';
                    $sAttr = $styleId !== '' ? ' s="' . $styleId . '"' : '';
                    $val = $rData[$cIdx] ?? null;

                    if ($val === null || $val === '') {
                        if ($sAttr !== '') {
                            $rowsXml .= '<c r="' . $colL . $outRow . '"' . $sAttr . '/>';
                        }
                    } else {
                        $strVal = (string)$val;
                        if (is_numeric($strVal) && !preg_match('/^0\d+$/', $strVal)) {
                            $rowsXml .= '<c r="' . $colL . $outRow . '"' . $sAttr . '><v>' . $strVal . '</v></c>';
                        } else {
                            $rowsXml .= '<c r="' . $colL . $outRow . '"' . $sAttr . ' t="inlineStr"><is><t>' . $this->escapeXmlValue($strVal) . '</t></is></c>';
                        }
                    }
                }
                $rowsXml .= '</row>';
                $outRow++;
            }

            $schoolSheetXml = $xmlPrefix . $rowsXml . $xmlSuffix;

            $cleanSheetName = preg_replace('/[\\\\\/?*\[\]:]/', '', $nombreIe ?: ($meta['codigo_ie'] ?: 'IE'));
            $safeTitle = substr('REPORTE - ' . ($cleanSheetName ?: 'IE'), 0, 30);
            $schoolWorkbookXml = str_replace(
                'name="REPORTE - __TPL_MAT__"',
                'name="' . $this->escapeXmlValue($safeTitle) . '"',
                $workbookXml
            );

            $xlsxBin = $this->buildXlsxBinaryInMemory($preCompressed, [
                'xl/workbook.xml' => $schoolWorkbookXml,
                'xl/worksheets/sheet1.xml' => $schoolSheetXml,
            ]);

            $zip->addFromString($zipInternalPath, $xlsxBin);
            $zip->setCompressionName($zipInternalPath, \ZipArchive::CM_STORE);
        }
    }

    /**
     * Aplica la paleta oficial y bordes de las 5 secciones observadas en las capturas de usuario.
     * 1. Detalle IE (B5:N6) en Blanco con CÓDIGO IE (F6) en Amarillo Chillón (#FFFF00)
     * 2. Resumen Matrícula 2026 (P5:AU6) en Amarillo Claro (#FFF2CC)
     * 3. Información 2025 con COMENTARIO (AV6) en Amarillo Chillón (#FFFF00)
     * 4. Plazas y Bolsa NEXUS (AW5:BK6) en Verde Menta (#E2EFDA)
     * 5. Fecha Bases (BL5:BN6) en Rosa/Melón Pastel (#FCE4D6)
     * Filas de Datos (Fila 7+) con bordes finos (#D9D9D9), altura 20 y fuentes Calibri.
     */
    private function aplicarEstilosOficialesMatricula(Spreadsheet $spreadsheet, int $dataStartRow = 7, int $dataEndRow = 9): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        $COLOR_BLANCO = 'FFFFFF';
        $COLOR_AMARILLO_CHILLON = 'FFFF00'; // CÓDIGO IE (F6) y COMENTARIO (AV6)
        $COLOR_AMARILLO_CLARO = 'FFF2CC';   // Resumen Matrícula, Grados, NEE, Totales
        $COLOR_VERDE_MENTA = 'E2EFDA';      // Plazas y Bolsa de horas registradas en NEXUS
        $COLOR_ROSA_PASTEL = 'FCE4D6';      // Fecha Bases (Nexus, Siagie, Escale)
        $COLOR_TITULO_BARRA = 'FFF2CC';     // Barra Resumen Matrícula Fila 4
        $COLOR_ROSA_BARRA = 'FCE4D6';       // Barra Plazas / Fechas Fila 4

        $BORDER_CABECERA = [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ];

        $BORDER_DATOS = [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D9D9D9']
            ]
        ];

        // Fila 2: Título principal
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getStyle('B2:BN2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Fila 4: Barras de sección
        $sheet->getRowDimension(4)->setRowHeight(18);
        $sheet->getStyle('P4:AR4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_TITULO_BARRA]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);
        $sheet->getStyle('AW4:BK4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_ROSA_BARRA]],
            'borders' => $BORDER_CABECERA
        ]);
        $sheet->getStyle('BL4:BN4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_ROSA_BARRA]],
            'borders' => $BORDER_CABECERA
        ]);

        // Fila 5: Encabezados de grupo
        $sheet->getRowDimension(5)->setRowHeight(28);

        $sheet->getStyle('B5:N5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_BLANCO]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        $sheet->getStyle('P5:AV5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_AMARILLO_CLARO]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        $sheet->getStyle('AW5:BK5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_VERDE_MENTA]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        $sheet->getStyle('BL5:BN5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_ROSA_PASTEL]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        // Fila 6: Nombres de Columnas
        $sheet->getRowDimension(6)->setRowHeight(36);

        $sheet->getStyle('B6:N6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_BLANCO]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // COLUMNA F6: CÓDIGO IE EN AMARILLO CHILLÓN (#FFFF00)
        $sheet->getStyle('F6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_AMARILLO_CHILLON]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        // P6:AU6 (Campos de Matrícula - Amarillo Claro)
        $sheet->getStyle('P6:AU6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_AMARILLO_CLARO]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // COLUMNA AV6: COMENTARIO EN AMARILLO CHILLÓN (#FFFF00)
        $sheet->getStyle('AV6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_AMARILLO_CHILLON]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // AW6:BK6 (Campos de Plazas NEXUS - Verde Menta)
        $sheet->getStyle('AW6:BK6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_VERDE_MENTA]],
            'font' => ['bold' => true, 'size' => 8, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // BL6:BN6 (Campos de Fecha Bases - Rosa Pastel)
        $sheet->getStyle('BL6:BN6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_ROSA_PASTEL]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        // Filas de datos (Fila 7 hasta el final)
        if ($dataEndRow >= $dataStartRow) {
            for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);

                $sheet->getStyle("A{$r}:BN{$r}")->applyFromArray([
                    'font' => ['size' => 9, 'name' => 'Calibri'],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => $BORDER_DATOS
                ]);

                $sheet->getStyle("B{$r}:F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("H{$r}:O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("P{$r}:AQ{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("AR{$r}:AU{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("AV{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("AW{$r}:BN{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
    }

    /**
     * Lector de XLSX ultraligero por streaming XML.
     * Consume menos de 40 MB de RAM y libera buffers inmediatamente.
     */
    private function leerFilasStreamingXlsx(string $filePath): ?array
    {
        if (!class_exists('\ZipArchive') || !class_exists('\XMLReader')) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        // 1. Leer sharedStrings.xml si existe
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = new \XMLReader();
            $xml->XML($sharedXml, null, LIBXML_NONET | LIBXML_COMPACT);
            unset($sharedXml);
            while ($xml->read()) {
                if ($xml->nodeType === \XMLReader::ELEMENT && $xml->name === 'si') {
                    $node = new \SimpleXMLElement($xml->readOuterXml());
                    $text = '';
                    if (isset($node->t)) {
                        $text = (string)$node->t;
                    } elseif (isset($node->r)) {
                        foreach ($node->r as $r) {
                            $text .= (string)$r->t;
                        }
                    }
                    $sharedStrings[] = $text;
                    unset($node);
                }
            }
            $xml->close();
        }

        // 2. Localizar la primera hoja (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($idx = 0; $idx < $zip->numFiles; $idx++) {
                $stat = $zip->statIndex($idx);
                if ($stat && str_starts_with($stat['name'], 'xl/worksheets/sheet') && str_ends_with($stat['name'], '.xml')) {
                    $sheetXml = $zip->getFromIndex($idx);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            unset($sharedStrings);
            return null;
        }

        $xml = new \XMLReader();
        $xml->XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT);
        unset($sheetXml);

        $rows = [];
        $currentRowIndex = 0;
        while ($xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT && ($xml->name === 'row' || $xml->localName === 'row')) {
                $rowXml = new \SimpleXMLElement($xml->readOuterXml());
                $rAttr = isset($rowXml['r']) ? (int)$rowXml['r'] : 0;
                if ($rAttr > 0) {
                    while ($currentRowIndex < $rAttr - 1) {
                        $rows[$currentRowIndex] = [];
                        $currentRowIndex++;
                    }
                }

                $rowData = [];
                $nextColIdx = 0;
                foreach ($rowXml->c as $c) {
                    $r = (string)$c['r'];
                    if ($r !== '' && preg_match('/^([A-Z]+)/i', $r, $matches)) {
                        $colLetters = strtoupper($matches[1]);
                        $colIdx = 0;
                        $len = strlen($colLetters);
                        for ($i = 0; $i < $len; $i++) {
                            $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - 64);
                        }
                        $colIdx -= 1;
                    } else {
                        $colIdx = $nextColIdx;
                    }
                    $nextColIdx = $colIdx + 1;

                    $type = (string)$c['t'];
                    if ($type === 'inlineStr') {
                        if (isset($c->is->t)) {
                            $val = (string)$c->is->t;
                        } elseif (isset($c->is->r)) {
                            $val = '';
                            foreach ($c->is->r as $run) {
                                $val .= (string)$run->t;
                            }
                        } else {
                            $val = '';
                        }
                    } else {
                        $val = isset($c->v) ? (string)$c->v : '';
                        if ($type === 's' && isset($sharedStrings[(int)$val])) {
                            $val = $sharedStrings[(int)$val];
                        }
                    }

                    $rowData[$colIdx] = $val;
                }
                unset($rowXml);

                if (!empty($rowData)) {
                    $maxKey = max(array_keys($rowData));
                    $denseRow = [];
                    for ($k = 0; $k <= $maxKey; $k++) {
                        $denseRow[$k] = $rowData[$k] ?? null;
                    }
                    $rows[$currentRowIndex] = $denseRow;
                } else {
                    $rows[$currentRowIndex] = [];
                }
                $currentRowIndex++;
            }
        }
        $xml->close();
        unset($sharedStrings);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return !empty($rows) ? $rows : null;
    }
}
