<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class ExcelFormatDetectorService
{
    public const TIPO_NEXUS = 'NEXUS';
    public const TIPO_MATRICULA = 'MATRICULA';
    public const TIPO_DESCONOCIDO = 'DESCONOCIDO';

    /**
     * Detecta el tipo de Excel analizando sus cabeceras y estructura.
     *
     * @param UploadedFile|string $file
     * @return array
     */
    public function detectarTipo($file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            $textBuffer = '';
            $maxInspectRows = min(10, count($rows));

            for ($i = 0; $i < $maxInspectRows; $i++) {
                if (isset($rows[$i])) {
                    $rowStr = implode(' ', array_filter($rows[$i], fn($v) => $v !== null && $v !== ''));
                    $textBuffer .= ' ' . strtoupper($rowStr);
                }
            }

            // Reglas de detección NEXUS
            $esNexus = (
                str_contains($textBuffer, 'CODIGO DE PLAZA') ||
                str_contains($textBuffer, 'CUADRO DE PLAZAS NEXUS') ||
                str_contains($textBuffer, 'SITUACION LABORAL') ||
                str_contains($textBuffer, 'NOMBRE DE LA UNIDAD EJECUTORA') ||
                (str_contains($textBuffer, 'CODMOD I.E.') && str_contains($textBuffer, 'MOTIVO DE VACANTE'))
            );

            // Reglas de detección REPORTE MATRÍCULA
            $esMatricula = (
                str_contains($textBuffer, 'DETALLE DE IIEE') ||
                str_contains($textBuffer, 'RESUMEN DE LA MATRICULA') ||
                str_contains($textBuffer, '0 GRADO PARA INICIAL') ||
                str_contains($textBuffer, 'PLAZAS Y BOLSA DE HORAS') ||
                str_contains($textBuffer, 'FECHA PADRON ESCALE')
            );

            if ($esNexus) {
                return [
                    'tipo' => self::TIPO_NEXUS,
                    'nombre_tipo' => 'Cuadro de Plazas NEXUS',
                    'descripcion' => 'Reporte oficial NEXUS de plazas y personal por Institución Educativa.',
                ];
            }

            if ($esMatricula) {
                return [
                    'tipo' => self::TIPO_MATRICULA,
                    'nombre_tipo' => 'Reporte de Matrícula y Niveles',
                    'descripcion' => 'Reporte consolidado de matrícula por grados, necesidades especiales y plazas.',
                ];
            }

            // Fallback: verificar por nombres de columnas conocidas
            if (str_contains($textBuffer, 'NOMBRE DE LA INSTITUCION EDUCATIVA')) {
                return [
                    'tipo' => self::TIPO_NEXUS,
                    'nombre_tipo' => 'Cuadro de Plazas NEXUS',
                    'descripcion' => 'Reporte detectado como NEXUS por columnas de instituciones.',
                ];
            }

            return [
                'tipo' => self::TIPO_MATRICULA, // Default en caso de duda
                'nombre_tipo' => 'Reporte de Matrícula y Niveles',
                'descripcion' => 'Formato general de matrícula procesado por el sistema.',
            ];

        } catch (Exception $e) {
            return [
                'tipo' => self::TIPO_DESCONOCIDO,
                'nombre_tipo' => 'Formato Desconocido',
                'error' => $e->getMessage(),
            ];
        }
    }
}
