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

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));
            $nivel = trim((string)($row[$nivelColIdx] ?? ''));
            $situacion = strtoupper(trim((string)($row[$situacionColIdx] ?? '')));

            if ($nombreIe === '' && $codMod === '' && $codLocal === '') continue;

            // Agrupar por nombre de IE (o código local/modular si no tiene nombre)
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

            // Formatear DNI para VACANTE según capturas de usuario
            $dniVal = trim((string)($row[$dniColIdx] ?? ''));
            if (str_contains($situacion, 'VACANTE') && ($dniVal === '' || $dniVal === '0')) {
                $dniVal = 'VACANTE';
            }

            $formatDateVal = function($val) {
                if ($val === null || trim((string)$val) === '' || $val === '0') return '';
                if (is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
                    try {
                        return Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                    } catch (\Throwable $e) {}
                }
                return trim((string)$val);
            };

            // Guardar filas de detalle para la vista previa interactiva en el frontend (hasta 120 filas)
            if (count($colegiosMap[$key]['filas_detalle']) < 120) {
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

    private function extraerColegiosMatricula(array $rows): array
    {
        $colsMap = [];
        $headerRowIdx = 5;

        for ($r = 0; $r < min(12, count($rows)); $r++) {
            $row = $rows[$r] ?? [];
            foreach ($row as $cIdx => $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $norm = $this->normalizeHeaderString((string)$val);
                    if (!isset($colsMap[$norm])) {
                        $colsMap[$norm] = $cIdx;
                    }
                }
            }
            $rowStr = $this->normalizeHeaderString(implode(' ', array_filter($row, fn($v) => $v !== null)));
            if (str_contains($rowStr, 'CENTROEDUCATIVO') || str_contains($rowStr, 'CODIGOIE')) {
                $headerRowIdx = $r;
            }
        }

        // Índices en arreglo 0-based: Col B=1, Col C=2, Col D=3, Col E=4, Col F=5, Col G=6
        $ieColIdx = $colsMap['CODIGOIE'] ?? 5; // Col F: CÓDIGO IE (Resaltado en amarillo oficial)
        $nombreColIdx = $colsMap['CENTROEDUCATIVO'] ?? $colsMap['NOMBREDEIE'] ?? 6; // Col G: CENTRO EDUCATIVO
        $nivelColIdx = $colsMap['NIVELEDUCATIVO'] ?? 2; // Col C: NIVEL EDUCATIVO
        $codModColIdx = $colsMap['CODIGOMODULAR'] ?? 4; // Col E: CÓDIGO MODULAR
        $codLocalColIdx = $colsMap['CODIGOLOCAL'] ?? 3; // Col D: CÓDIGO LOCAL

        $mat2026ColIdx = $colsMap['TOTALMATRICULA2026'] ?? 43; // Col AR (idx 43)
        $mat2025ColIdx = $colsMap['TOTALMATRICULA2025'] ?? 44; // Col AS (idx 44)
        $seccionesColIdx = $colsMap['SECCIONESNECESARIAS2025'] ?? 45; // Col AT (idx 45)
        $avanceColIdx = $colsMap['DEAVANCEDEDEMATRICULA'] ?? $colsMap['DEAVANCEMATRICULA'] ?? 46; // Col AU (idx 46)
        $comentarioColIdx = $colsMap['COMENTARIO'] ?? 47; // Col AV (idx 47)
        $plazasColIdx = $colsMap['TOTALPLAZASENSSEE'] ?? 61; // Col BJ (idx 61)
        $bolsaColIdx = $colsMap['NRODEBHEBRSECUNDARIA'] ?? 62; // Col BK (idx 62)
        $fechaNexusColIdx = $colsMap['FECHANEXUS'] ?? 63; // Col BL (idx 63)
        $fechaSiagieColIdx = $colsMap['FECHASIAGIE'] ?? 64; // Col BM (idx 64)
        $fechaEscaleColIdx = $colsMap['FECHAPADRONESCALE'] ?? 65; // Col BN (idx 65)

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

            $isSinRegistro = ($codIe === '' || strtoupper($codIe) === 'SIN REGISTRO');
            // Criterio de filtrado principal: CÓDIGO IE agrupa todos los niveles (Inicial, Primaria, Secundaria)
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

                // Formatear fechas legibles si es timestamp serial numérico de Excel
                if (str_contains($headerName, 'FECHA') && is_numeric($val) && (float)$val > 1000 && (float)$val < 100000) {
                    try {
                        $val = Date::excelToDateTimeObject((float)$val)->format('n/j/Y');
                    } catch (\Throwable $e) {}
                }

                // Ajuste de VACANTE en DNI (Col AJ)
                if ($cIdx === $dniColIdx && $isRowVacante && (trim((string)$val) === '' || trim((string)$val) === '0')) {
                    $val = 'VACANTE';
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

            // Alineaciones oficiales exactas según capturas
            $outSheet->getStyle("A{$currentRow}:E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("F{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // CODMOD
            $outSheet->getStyle("L{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // COD LOCAL, CLAVE8
            $outSheet->getStyle("O{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NIVEL EDUCATIVO
            $outSheet->getStyle("P{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // NOMBRE IE
            $outSheet->getStyle("Q{$currentRow}:S{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // JEC, PLAZA, AIRHSP
            $outSheet->getStyle("U{$currentRow}:W{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // CARGO
            $outSheet->getStyle("X{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // SITUACION
            $outSheet->getStyle("Y{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // MOTIVO
            $outSheet->getStyle("Z{$currentRow}:AD{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $outSheet->getStyle("AE{$currentRow}:AF{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // FECHAS
            $outSheet->getStyle("AJ{$currentRow}:AK{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // DNI, COD MOD
            $outSheet->getStyle("AL{$currentRow}:AM{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NACIMIENTO, SEXO
            $outSheet->getStyle("AN{$currentRow}:AP{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // APELLIDOS Y NOMBRES
            $outSheet->getStyle("AX{$currentRow}:BB{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // ESTUDIOS Y ESPECIALIDAD

            $outSheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        // Anchos de columna oficiales exactos observados en las capturas y plantilla NEXUS
        $officialWidths = [
            'A' => 23, 'B' => 33, 'C' => 32, 'D' => 16, 'E' => 16, 'F' => 24, 'G' => 16, 'H' => 19, 'I' => 16,
            'J' => 17, 'K' => 18, 'L' => 16, 'M' => 16, 'N' => 16, 'O' => 20, 'P' => 38, 'Q' => 16, 'R' => 19,
            'S' => 17, 'T' => 16, 'U' => 22, 'V' => 26, 'W' => 24, 'X' => 21, 'Y' => 34, 'Z' => 26, 'AA' => 20,
            'AB' => 24, 'AC' => 18, 'AD' => 22, 'AE' => 16, 'AF' => 16, 'AG' => 20, 'AH' => 16, 'AI' => 22, 'AJ' => 22,
            'AK' => 18, 'AL' => 18, 'AM' => 16, 'AN' => 22, 'AO' => 22, 'AP' => 24, 'AQ' => 22, 'AR' => 18, 'AS' => 18,
            'AT' => 18, 'AU' => 20, 'AV' => 18, 'AW' => 18, 'AX' => 22, 'AY' => 36, 'AZ' => 26, 'BA' => 22, 'BB' => 32,
            'BC' => 18, 'BD' => 18, 'BE' => 26, 'BF' => 18, 'BG' => 22, 'BH' => 18, 'BI' => 24, 'BJ' => 24, 'BK' => 16,
            'BL' => 24, 'BM' => 20, 'BN' => 20
        ];
        foreach ($officialWidths as $colL => $w) {
            $outSheet->getColumnDimension($colL)->setWidth($w);
        }

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
        $rows = $sourceSheet->toArray(null, false, false, false);

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
        $nombreEncontrado = '';
        $cleanTarget = trim($nombreColegio);

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

            $nombreIe = trim((string)($row[$ieColIdx] ?? ''));
            $codMod = trim((string)($row[$codModColIdx] ?? ''));
            $codLocal = trim((string)($row[$codLocalColIdx] ?? ''));

            if (
                strcasecmp($nombreIe, $cleanTarget) === 0 ||
                strcasecmp($codMod, $cleanTarget) === 0 ||
                strcasecmp($codLocal, $cleanTarget) === 0 ||
                (strlen($cleanTarget) >= 4 && str_contains(strtoupper($nombreIe), strtoupper($cleanTarget)))
            ) {
                $filteredRows[] = $row;
                if ($nombreEncontrado === '' || strlen($nombreIe) > strlen($nombreEncontrado)) {
                    $nombreEncontrado = $nombreIe;
                }
            }
        }

        $nombreFinal = $nombreEncontrado ?: $cleanTarget;
        return $this->generarExcelNexusColegioDesdeFilas($nombreFinal, $filteredRows, $headers, $colsMap);
    }

    /**
     * Construye y guarda el archivo Excel REPORTE MATRÍCULA para un colegio a partir de sus filas filtradas.
     * Preserva exactamente los encabezados (Filas 1-6), la columna F ("CÓDIGO IE") en amarillo oficial,
     * las 5 secciones coloreadas y las filas de datos a partir de la Fila 7 con todos los niveles (Inicial, Primaria, Secundaria).
     */
    public function generarExcelMatriculaColegioDesdeFilas(string $nombreColegio, string $codIe, array $matchingRowIndices, $sourceSheet): array
    {
        $highestColNum = Coordinate::columnIndexFromString($sourceSheet->getHighestColumn());
        $headerRowIdx = 6;

        $outSpreadsheet = new Spreadsheet();
        $outSheet = $outSpreadsheet->getActiveSheet();

        $cleanName = preg_replace('/[\\\\\/?*\[\]:]/', '', $nombreColegio ?: $codIe);
        $safeTitle = substr('REPORTE - ' . ($cleanName ?: 'IE'), 0, 30);
        $outSheet->setTitle($safeTitle);

        // 1. Copiar anchos de columna oficiales
        for ($c = 1; $c <= $highestColNum; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $dim = $sourceSheet->getColumnDimension($colLetter);
            if ($dim->getWidth() > 0) {
                $outSheet->getColumnDimension($colLetter)->setWidth($dim->getWidth());
            } else {
                $outSheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        // 2. Copiar celdas combinadas de la cabecera (filas 1 a 6)
        foreach ($sourceSheet->getMergeCells() as $mergeRange) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $mergeRange, $m)) {
                $endRow = (int)$m[4];
                if ($endRow <= $headerRowIdx) {
                    $outSheet->mergeCells($mergeRange);
                }
            }
        }

        // 3. Copiar contenido y estilos oficiales de las filas de cabecera (Filas 1 a 6)
        for ($r = 1; $r <= $headerRowIdx; $r++) {
            $rowDim = $sourceSheet->getRowDimension($r);
            if ($rowDim->getRowHeight() > 0) {
                $outSheet->getRowDimension($r)->setRowHeight($rowDim->getRowHeight());
            }
            for ($c = 1; $c <= $highestColNum; $c++) {
                $srcCell = $sourceSheet->getCell([$c, $r]);
                $dstCell = $outSheet->getCell([$c, $r]);
                $dstCell->setValue($srcCell->getValue());
            }
        }

        // 4. Copiar filas de datos filtradas (a partir de la fila 7 en adelante)
        $outRow = $headerRowIdx + 1;
        foreach ($matchingRowIndices as $srcRow) {
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
            }
            $outRow++;
        }

        // 5. Aplicar la paleta de colores oficiales y bordes idéntica a las capturas (5 secciones coloreadas)
        $this->aplicarEstilosOficialesMatricula($outSpreadsheet, $headerRowIdx + 1, $outRow - 1);

        // Guardar archivo individual
        $outputDir = $this->getWritableDir('matricula_colegios');
        $safeName = preg_replace('/[^\w\s\.-]/u', '_', $nombreColegio ?: $codIe);
        $safeName = preg_replace('/\s+/', ' ', trim($safeName));
        if ($safeName === '') $safeName = $codIe ?: 'IE';
        $filename = "REPORTE - I.E. {$safeName}.xlsx";
        $fullPath = "{$outputDir}/{$filename}";

        $writer = new Xlsx($outSpreadsheet);
        $writer->save($fullPath);

        $outSpreadsheet->disconnectWorksheets();
        unset($outSpreadsheet, $writer);

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

        // Identificar la fila de cabeceras principales
        $headerRowIdx = 6;
        for ($r = 1; $r <= min(10, $highestRow); $r++) {
            $rowText = '';
            for ($c = 1; $c <= $highestColNum; $c++) {
                $val = $sourceSheet->getCell([$c, $r])->getValue();
                if ($val !== null) $rowText .= ' ' . $this->normalizeHeaderString((string)$val);
            }
            if (str_contains($rowText, 'CODIGOIE') || str_contains($rowText, 'CENTROEDUCATIVO')) {
                $headerRowIdx = $r;
                break;
            }
        }

        // Mapear columnas en la fila de cabecera
        $colsMap = [];
        for ($c = 1; $c <= $highestColNum; $c++) {
            $val = $sourceSheet->getCell([$c, $headerRowIdx])->getValue();
            if ($val !== null && trim((string)$val) !== '') {
                $colsMap[$this->normalizeHeaderString((string)$val)] = $c;
            }
        }

        $ieCol = $colsMap['CODIGOIE'] ?? 6; // Col F (CÓDIGO IE)
        $nombreCol = $colsMap['CENTROEDUCATIVO'] ?? $colsMap['NOMBREDEIE'] ?? 7; // Col G (CENTRO EDUCATIVO)
        $codModCol = $colsMap['CODIGOMODULAR'] ?? 5; // Col E (CÓDIGO MODULAR)
        $codLocalCol = $colsMap['CODIGOLOCAL'] ?? 4; // Col D (CÓDIGO LOCAL)

        // Encontrar filas de datos que pertenecen a este colegio por CÓDIGO IE (prioridad) o Local/Nombre
        $matchingRows = [];
        $nombreEncontrado = '';
        $codIeEncontrado = '';

        for ($r = $headerRowIdx + 1; $r <= $highestRow; $r++) {
            $codIeVal = trim((string)$sourceSheet->getCell([$ieCol, $r])->getValue());
            $nombreIeVal = trim((string)$sourceSheet->getCell([$nombreCol, $r])->getValue());
            $codModVal = trim((string)$sourceSheet->getCell([$codModCol, $r])->getValue());
            $codLocalVal = trim((string)$sourceSheet->getCell([$codLocalCol, $r])->getValue());

            if ($codIeVal === '' && $nombreIeVal === '' && $codModVal === '') continue;

            $esMatch = false;

            // 1. Coincidencia exacta por Código IE
            if ($codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO' && strcasecmp($codIeVal, $codigoColegio) === 0) {
                $esMatch = true;
            } elseif (
                strcasecmp($codLocalVal, $codigoColegio) === 0 ||
                strcasecmp("LOCAL_{$codLocalVal}", $codigoColegio) === 0 ||
                strcasecmp($codModVal, $codigoColegio) === 0 ||
                strcasecmp($nombreIeVal, $codigoColegio) === 0
            ) {
                $esMatch = true;
            }

            if ($esMatch) {
                $matchingRows[] = $r;
                if ($nombreEncontrado === '' || strlen($nombreIeVal) > strlen($nombreEncontrado)) {
                    $nombreEncontrado = $nombreIeVal;
                }
                if ($codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO') {
                    $codIeEncontrado = $codIeVal;
                }
            }
        }

        // Si no hubo coincidencia exacta directa, buscar por coincidencia parcial en nombre
        if (empty($matchingRows)) {
            for ($r = $headerRowIdx + 1; $r <= $highestRow; $r++) {
                $codIeVal = trim((string)$sourceSheet->getCell([$ieCol, $r])->getValue());
                $nombreIeVal = trim((string)$sourceSheet->getCell([$nombreCol, $r])->getValue());
                $codModVal = trim((string)$sourceSheet->getCell([$codModCol, $r])->getValue());
                $codLocalVal = trim((string)$sourceSheet->getCell([$codLocalCol, $r])->getValue());

                if (
                    ($codIeVal !== '' && str_contains(strtoupper($codIeVal), strtoupper($codigoColegio))) ||
                    ($nombreIeVal !== '' && str_contains(strtoupper($nombreIeVal), strtoupper($codigoColegio)))
                ) {
                    $matchingRows[] = $r;
                    if ($nombreEncontrado === '') $nombreEncontrado = $nombreIeVal;
                    if ($codIeEncontrado === '' && $codIeVal !== '' && strtoupper($codIeVal) !== 'SIN REGISTRO') {
                        $codIeEncontrado = $codIeVal;
                    }
                }
            }
        }

        $res = $this->generarExcelMatriculaColegioDesdeFilas($nombreEncontrado ?: $codigoColegio, $codIeEncontrado ?: $codigoColegio, $matchingRows, $sourceSheet);

        $sourceSpreadsheet->disconnectWorksheets();
        unset($sourceSpreadsheet);

        return $res;
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
            // Flujo optimizado para Reporte de Matrícula (3 Niveles) agrupado por CÓDIGO IE
            @set_time_limit(600);
            @ini_set('memory_limit', '512M');

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

            $headerRowIdx = 6;
            $ieCol = 6; // Col F: CÓDIGO IE
            $nombreCol = 7; // Col G: CENTRO EDUCATIVO
            $codModCol = 5; // Col E: CÓDIGO MODULAR
            $codLocalCol = 4; // Col D: CÓDIGO LOCAL

            // Agrupar filas por clave de colegio (CÓDIGO IE como clave principal)
            $matchingRowsPerSchool = [];
            $schoolMeta = [];

            for ($r = $headerRowIdx + 1; $r <= $highestRow; $r++) {
                $cIe = trim((string)$sourceSheet->getCell([$ieCol, $r])->getValue());
                $cLocal = trim((string)$sourceSheet->getCell([$codLocalCol, $r])->getValue());
                $cMod = trim((string)$sourceSheet->getCell([$codModCol, $r])->getValue());
                $cNom = trim((string)$sourceSheet->getCell([$nombreCol, $r])->getValue());

                if ($cIe === '' && $cNom === '' && $cLocal === '') continue;

                $isSinReg = ($cIe === '' || strtoupper($cIe) === 'SIN REGISTRO');
                $k = !$isSinReg ? $cIe : ($cLocal !== '' ? "LOCAL_{$cLocal}" : ($cMod !== '' ? "MOD_{$cMod}" : "NOM_{$cNom}"));

                $matchingRowsPerSchool[$k][] = $r;

                if (!isset($schoolMeta[$k])) {
                    $schoolMeta[$k] = [
                        'codigo_ie' => !$isSinReg ? $cIe : '',
                        'codigo_local' => $cLocal,
                        'nombre' => $cNom,
                        'codmod' => $cMod
                    ];
                } elseif ($cNom !== '' && strlen($cNom) > strlen($schoolMeta[$k]['nombre'])) {
                    $schoolMeta[$k]['nombre'] = $cNom;
                }
            }

            foreach ($schoolMeta as $k => $meta) {
                $rowsForSchool = $matchingRowsPerSchool[$k] ?? [];
                if (empty($rowsForSchool)) continue;

                $codCode = !empty($meta['codigo_local']) ? trim((string)$meta['codigo_local']) : (!empty($meta['codigo_ie']) ? trim((string)$meta['codigo_ie']) : '000000');
                if (strlen($codCode) < 6 && is_numeric($codCode)) {
                    $codCode = str_pad($codCode, 6, '0', STR_PAD_LEFT);
                }
                $nombreIe = trim((string)($meta['nombre'] ?? 'IE'));
                if ($nombreIe === '') $nombreIe = "IE_{$codCode}";

                $folderName = "{$codCode} - {$nombreIe}";
                $folderNameSafe = preg_replace('/[^\w\s\.-]/u', '_', $folderName);
                $folderNameSafe = preg_replace('/\s+/', ' ', trim($folderNameSafe));

                try {
                    $excelRes = $this->generarExcelMatriculaColegioDesdeFilas($nombreIe, $meta['codigo_ie'], $rowsForSchool, $sourceSheet);
                    if (file_exists($excelRes['path'])) {
                        $zipInternalPath = "{$basePath}/{$folderNameSafe}/{$excelRes['filename']}";
                        $zip->addFile($excelRes['path'], $zipInternalPath);
                        $generados[] = $excelRes['path'];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            $sourceSpreadsheet->disconnectWorksheets();
            unset($sourceSpreadsheet);
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

        // B5:N5 (DETALLE DE IIEE - Blanco)
        $sheet->getStyle('B5:N5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_BLANCO]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        // P5:AV5 (Grados, NEE, Totales e Información 2025 - Amarillo Claro)
        $sheet->getStyle('P5:AV5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_AMARILLO_CLARO]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // AW5:BK5 (Plazas y Bolsa NEXUS - Verde Menta)
        $sheet->getStyle('AW5:BK5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_VERDE_MENTA]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // BL5:BN5 (Fecha Bases - Rosa Pastel)
        $sheet->getStyle('BL5:BN5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_ROSA_PASTEL]],
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $BORDER_CABECERA
        ]);

        // Fila 6: Nombres de Columnas
        $sheet->getRowDimension(6)->setRowHeight(36);

        // B6:N6 (Detalle IE - Blanco)
        $sheet->getStyle('B6:N6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_BLANCO]],
            'font' => ['bold' => true, 'size' => 8.5, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $BORDER_CABECERA
        ]);

        // ¡¡¡COLUMNA F6: CÓDIGO IE EN AMARILLO CHILLÓN (#FFFF00)!!!
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

        // ¡¡¡COLUMNA AV6: COMENTARIO EN AMARILLO CHILLÓN (#FFFF00)!!!
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

                // Estilo general de la fila
                $sheet->getStyle("A{$r}:BN{$r}")->applyFromArray([
                    'font' => ['size' => 9, 'name' => 'Calibri'],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => $BORDER_DATOS
                ]);

                // Centrado de columnas específicas
                $sheet->getStyle("B{$r}:F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // Nombre IE
                $sheet->getStyle("H{$r}:O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("P{$r}:AQ{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Matrícula
                $sheet->getStyle("AR{$r}:AU{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Totales y %
                $sheet->getStyle("AV{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // Comentario
                $sheet->getStyle("AW{$r}:BN{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Plazas y Fechas
            }
        }
    }
}

