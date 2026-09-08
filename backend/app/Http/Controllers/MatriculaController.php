<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MatriculaService;
use App\Services\ExcelFormatTrainingService;
use App\Services\FiltradoColegioService;
use ZipArchive;

class MatriculaController extends Controller
{
    public function __construct(
        protected MatriculaService $service,
        protected ExcelFormatTrainingService $trainingService,
        protected FiltradoColegioService $filtradoColegioService
    ) {}

    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            return response()->json([
                'success' => true,
                'preview' => $this->service->previewArchivo($request->file('archivo')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al generar vista previa: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function procesar(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:20480',
            'columnas_resaltadas' => 'nullable|array',
        ]);

        $archivo = $request->file('archivo');
        $columnasResaltadas = $request->input('columnas_resaltadas', []);

        try {
            // 1. Guardar copia temporal PRIMERO para asegurar disponibilidad
            $tempPath = $this->saveUploadedTempFile($archivo);

            // 2. Procesar algoritmo principal de matrícula
            $resultado = $this->service->procesarArchivo($archivo, $columnasResaltadas);

            // 3. Extraer colegios desde la copia guardada
            $colegiosData = $this->filtradoColegioService->procesarArchivoColegios($tempPath);

            return response()->json([
                'success' => true,
                'mensaje' => 'Archivo procesado correctamente',
                'nivel' => $resultado['nivel'] ?? null,
                'estadisticas' => $resultado['estadisticas'],
                'archivos' => $resultado['archivos'],
                'colegios' => $colegiosData['colegios'] ?? [],
                'tipo_excel' => $colegiosData['tipo_excel'] ?? 'MATRICULA',
                'errores' => $resultado['errores'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al procesar: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function saveUploadedTempFile($file): string
    {
        $sourcePath = is_string($file) ? $file : ($file->getRealPath() ?: $file->getPathname());

        $dir = storage_path('app');
        if (!@is_dir($dir) || !@is_writable($dir)) {
            @mkdir($dir, 0755, true);
        }
        $targetDir = (@is_dir($dir) && @is_writable($dir)) ? $dir : sys_get_temp_dir();
        $targetPath = $targetDir . '/temp_filtrado_colegio_uploaded.xlsx';

        if (file_exists($sourcePath)) {
            @copy($sourcePath, $targetPath);
        }

        return $targetPath;
    }

    private function getUploadedTempFile(): ?string
    {
        $path1 = storage_path('app/temp_filtrado_colegio_uploaded.xlsx');
        if (file_exists($path1)) return $path1;
        $path2 = sys_get_temp_dir() . '/temp_filtrado_colegio_uploaded.xlsx';
        if (file_exists($path2)) return $path2;
        return null;
    }

    /**
     * Endpoint dedicado para el Módulo Aislado de Filtrado por Colegio.
     */
    public function procesarFiltradoColegio(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|max:30720',
        ]);

        try {
            $archivo = $request->file('archivo');

            // 1. Guardar copia temporal PRIMERO
            $tempPath = $this->saveUploadedTempFile($archivo);

            // 2. Procesar filtrado usando la ruta de archivo ya guardada
            $resultado = $this->filtradoColegioService->procesarArchivoColegios($tempPath);

            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al procesar archivo para filtrado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint dedicado para exportar/descargar el Excel por colegio (NEXUS o REPORTE MATRICULA).
     */
    public function exportarFiltradoColegio(Request $request)
    {
        $request->validate([
            'colegio' => 'required|string',
            'tipo_excel' => 'required|string',
        ]);

        $colegio = $request->input('colegio');
        $tipoExcel = $request->input('tipo_excel');
        $uploadedPath = $this->getUploadedTempFile();

        if (!$uploadedPath || !file_exists($uploadedPath)) {
            return response()->json(['error' => 'No hay un archivo cargado recientemente en la pestaña de filtrado.'], 404);
        }

        try {
            if ($tipoExcel === 'NEXUS') {
                $res = $this->filtradoColegioService->exportarNexusColegio($uploadedPath, $colegio);
            } else {
                $res = $this->filtradoColegioService->exportarMatriculaColegio($uploadedPath, $colegio);
            }

            return response()->download($res['path'], $res['filename']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al exportar archivo por colegio: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para exportación masiva en archivo ZIP de todos los colegios procesados.
     */
    public function exportarZipColegios(Request $request)
    {
        $uploadedPath = $this->getUploadedTempFile();

        if (!$uploadedPath || !file_exists($uploadedPath)) {
            return response()->json(['error' => 'No hay un archivo cargado recientemente para exportar el paquete ZIP.'], 404);
        }

        try {
            $res = $this->filtradoColegioService->exportarZipColegios($uploadedPath);
            return response()->download($res['path'], $res['filename']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al exportar paquete ZIP por colegios: ' . $e->getMessage()], 500);
        }
    }

    public function descargar($filename)
    {
        $path = base64_decode($filename);
        $fullPath = storage_path('app/matricula_output/' . $path);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return response()->download($fullPath);
    }

    public function descargarPdf($filename)
    {
        $path = base64_decode($filename);
        $fullPath = storage_path('app/matricula_output/' . $path);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        [$pdfContent, $pdfName] = $this->service->generarPdfDesdeExcel($fullPath);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $pdfName . '"',
        ]);
    }

    public function descargarZip(Request $request)
    {
        $outputDir = storage_path('app/matricula_output');

        if (!is_dir($outputDir)) {
            return response()->json(['error' => 'Sin archivos generados'], 404);
        }

        $zipPath = storage_path('app/matricula_exportacion_' . now()->format('Ymd_His') . '.zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getRealPath(), strlen($outputDir) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function listarFormatos()
    {
        return response()->json([
            'success' => true,
            'formatos' => $this->trainingService->listProfiles(),
        ]);
    }

    public function analizarFormato(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            return response()->json([
                'success' => true,
                'analisis' => $this->trainingService->analyzeWorkbook($request->file('archivo')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al analizar el formato: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function guardarFormato(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'nivel' => 'required|string|max:50',
            'columns' => 'required|array',
            'match_keywords' => 'nullable|array',
            'data_start_row' => 'nullable|integer|min:1',
            'header_row_index' => 'nullable|integer|min:1',
            'subheader_row_index' => 'nullable|integer|min:1',
        ]);

        try {
            return response()->json([
                'success' => true,
                'mensaje' => 'Formato entrenado guardado correctamente',
                'formato' => $this->trainingService->saveProfile($request->all()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al guardar el formato: ' . $e->getMessage(),
            ], 500);
        }
    }
}
