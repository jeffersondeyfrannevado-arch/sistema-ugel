<?php

namespace Tests\Unit;

use App\Services\ExcelFormatTrainingService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class ExcelFormatTrainingServiceTest extends TestCase
{
    public function test_it_saves_and_lists_profiles(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_profiles_' . uniqid() . '.json';
        $service = new ExcelFormatTrainingService($path);

        $profile = $service->saveProfile([
            'nombre' => 'Formato Secundaria Ajustado',
            'nivel' => 'SECUNDARIA',
            'match_keywords' => ['SECUNDARIA', 'MATRICULA'],
            'data_start_row' => 9,
            'header_row_index' => 7,
            'subheader_row_index' => 8,
            'columns' => [
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
                'MATRICULA_EN_PROCESO' => 16,
                'DNI_VALIDADO' => 19,
                'DNI_SIN_VALIDAR' => 20,
                'SIN_DNI' => 21,
                'TOTAL_GRADOS' => 22,
                'TOTAL_SECCIONES' => 23,
                'NOM_GENERADAS' => 24,
                'NOM_APROBADAS' => 25,
                'NOM_RECTIFICAR' => 26,
                'P1H' => 27,
                'P1M' => 28,
                'P2H' => 29,
                'P2M' => 30,
                'P3H' => 31,
                'P3M' => 32,
                'P4H' => 33,
                'P4M' => 34,
                'P5H' => 35,
                'P5M' => 36,
            ],
        ]);

        $profiles = $service->listProfiles();

        $this->assertCount(1, $profiles);
        $this->assertSame('Formato Secundaria Ajustado', $profile['nombre']);
        $this->assertSame('SECUNDARIA', $profiles[0]['nivel']);
        $this->assertSame(16, $profiles[0]['columns']['MATRICULA_EN_PROCESO']);

        @unlink($path);
    }

    public function test_it_detects_accented_headers_in_workbook_analysis(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A8', 'DRE');
        $sheet->setCellValue('B8', 'UGEL');
        $sheet->setCellValue('C8', 'Departamento');
        $sheet->setCellValue('D8', 'Provincia');
        $sheet->setCellValue('E8', 'Distrito');
        $sheet->setCellValue('F8', 'Centro Poblado');
        $sheet->setCellValue('G8', 'Cód. Mod.');
        $sheet->setCellValue('H8', 'Anexo');
        $sheet->setCellValue('I8', 'Nombre de IE');
        $sheet->setCellValue('J8', 'Nivel');
        $sheet->setCellValue('K8', 'Modalidad');
        $sheet->setCellValue('L8', 'Tipo IE');
        $sheet->setCellValue('M8', 'Total de estudiantes matriculados (*)');
        $sheet->setCellValue('O8', 'Matrícula Definitiva');
        $sheet->setCellValue('Q8', 'Matricula En Proceso');
        $sheet->setCellValue('T8', 'DNI Validado');
        $sheet->setCellValue('U8', 'DNI sin Validar');
        $sheet->setCellValue('V8', 'Registrado sin DNI');
        $sheet->setCellValue('W8', 'Total Grados');
        $sheet->setCellValue('X8', 'Total Secciones');
        $sheet->setCellValue('Y8', 'Nòminas de Matrícula');
        $sheet->setCellValue('Y9', 'Generadas');
        $sheet->setCellValue('Z9', 'Aprobadas');
        $sheet->setCellValue('AA9', 'Por Rectificar');
        $sheet->setCellValue('AB8', 'Primero');
        $sheet->setCellValue('AB9', 'Hombres');
        $sheet->setCellValue('AC9', 'Mujeres');
        $sheet->setCellValue('AD8', 'Segundo');
        $sheet->setCellValue('AD9', 'Hombres');
        $sheet->setCellValue('AE9', 'Mujeres');
        $sheet->setCellValue('AF8', 'Tercero');
        $sheet->setCellValue('AF9', 'Hombres');
        $sheet->setCellValue('AG9', 'Mujeres');
        $sheet->setCellValue('AH8', 'Cuarto');
        $sheet->setCellValue('AH9', 'Hombres');
        $sheet->setCellValue('AI9', 'Mujeres');
        $sheet->setCellValue('AJ8', 'Quinto');
        $sheet->setCellValue('AJ9', 'Hombres');
        $sheet->setCellValue('AK9', 'Mujeres');
        $sheet->setCellValue('AL8', 'Sexto');
        $sheet->setCellValue('AL9', 'Hombres');
        $sheet->setCellValue('AM9', 'Mujeres');
        $sheet->setCellValue('A10', 'DRE Piura');
        $sheet->setCellValue('J10', 'B0 - Primaria');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_analysis_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $profilesPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_profiles_' . uniqid() . '.json';
        $service = new ExcelFormatTrainingService($profilesPath);
        $file = new UploadedFile($path, basename($path), null, null, true);

        $analysis = $service->analyzeWorkbook($file);

        $this->assertSame(6, $analysis['columnas_sugeridas']['COD_MOD']);
        $this->assertSame(14, $analysis['columnas_sugeridas']['MATRICULA_DEFINITIVA']);
        $this->assertSame(16, $analysis['columnas_sugeridas']['MATRICULA_EN_PROCESO']);
        $this->assertSame(27, $analysis['columnas_sugeridas']['PR1H']);
        $this->assertSame(38, $analysis['columnas_sugeridas']['PR6M']);

        @unlink($path);
        @unlink($profilesPath);
    }

    public function test_it_allows_duplicate_name_when_mapping_is_identical(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_profiles_' . uniqid() . '.json';
        $service = new ExcelFormatTrainingService($path);

        $basePayload = [
            'nombre' => 'Formato PRIMARIA',
            'nivel' => 'PRIMARIA',
            'match_keywords' => ['PRIMARIA'],
            'data_start_row' => 10,
            'header_row_index' => 8,
            'subheader_row_index' => 9,
            'columns' => [
                'DRE' => 0, 'UGEL' => 1, 'DEPARTAMENTO' => 2, 'PROVINCIA' => 3, 'DISTRITO' => 4,
                'CENTRO_POBLADO' => 5, 'COD_MOD' => 6, 'ANEXO' => 7, 'NOMBRE_IE' => 8, 'NIVEL' => 9,
                'MODALIDAD' => 10, 'TIPO_IE' => 11, 'TOTAL_MATRICULADOS' => 12, 'MATRICULA_DEFINITIVA' => 14,
                'MATRICULA_EN_PROCESO' => 16, 'DNI_VALIDADO' => 19, 'DNI_SIN_VALIDAR' => 20, 'SIN_DNI' => 21,
                'TOTAL_GRADOS' => 22, 'TOTAL_SECCIONES' => 23, 'NOM_GENERADAS' => 24, 'NOM_APROBADAS' => 25,
                'NOM_RECTIFICAR' => 26, 'PR1H' => 27, 'PR1M' => 28, 'PR2H' => 29, 'PR2M' => 30,
                'PR3H' => 31, 'PR3M' => 32, 'PR4H' => 33, 'PR4M' => 34, 'PR5H' => 35, 'PR5M' => 36,
                'PR6H' => 37, 'PR6M' => 38,
            ],
        ];

        $first = $service->saveProfile($basePayload);
        $second = $service->saveProfile($basePayload);

        $this->assertSame('Formato PRIMARIA', $first['nombre']);
        $this->assertSame('Formato PRIMARIA', $second['nombre']);
        $this->assertCount(2, $service->listProfiles());

        @unlink($path);
    }

    public function test_it_renames_duplicate_name_when_mapping_differs(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_profiles_' . uniqid() . '.json';
        $service = new ExcelFormatTrainingService($path);

        $baseColumns = [
            'DRE' => 0, 'UGEL' => 1, 'DEPARTAMENTO' => 2, 'PROVINCIA' => 3, 'DISTRITO' => 4,
            'CENTRO_POBLADO' => 5, 'COD_MOD' => 6, 'ANEXO' => 7, 'NOMBRE_IE' => 8, 'NIVEL' => 9,
            'MODALIDAD' => 10, 'TIPO_IE' => 11, 'TOTAL_MATRICULADOS' => 12, 'MATRICULA_DEFINITIVA' => 14,
            'MATRICULA_EN_PROCESO' => 16, 'DNI_VALIDADO' => 19, 'DNI_SIN_VALIDAR' => 20, 'SIN_DNI' => 21,
            'TOTAL_GRADOS' => 22, 'TOTAL_SECCIONES' => 23, 'NOM_GENERADAS' => 24, 'NOM_APROBADAS' => 25,
            'NOM_RECTIFICAR' => 26, 'PR1H' => 27, 'PR1M' => 28, 'PR2H' => 29, 'PR2M' => 30,
            'PR3H' => 31, 'PR3M' => 32, 'PR4H' => 33, 'PR4M' => 34, 'PR5H' => 35, 'PR5M' => 36,
            'PR6H' => 37, 'PR6M' => 38,
        ];

        $service->saveProfile([
            'nombre' => 'Formato PRIMARIA',
            'nivel' => 'PRIMARIA',
            'match_keywords' => ['PRIMARIA'],
            'data_start_row' => 10,
            'header_row_index' => 8,
            'subheader_row_index' => 9,
            'columns' => $baseColumns,
        ]);

        $changedColumns = $baseColumns;
        $changedColumns['MATRICULA_EN_PROCESO'] = 17;

        $second = $service->saveProfile([
            'nombre' => 'Formato PRIMARIA',
            'nivel' => 'PRIMARIA',
            'match_keywords' => ['PRIMARIA'],
            'data_start_row' => 10,
            'header_row_index' => 8,
            'subheader_row_index' => 9,
            'columns' => $changedColumns,
        ]);

        $this->assertSame('Formato PRIMARIA - Variante 2', $second['nombre']);
        $this->assertCount(2, $service->listProfiles());

        @unlink($path);
    }

    public function test_it_ignores_profiles_from_a_different_level(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'excel_profiles_' . uniqid() . '.json';
        $service = new ExcelFormatTrainingService($path);

        $profile = $service->saveProfile([
            'nombre' => 'Formato PRIMARIA',
            'nivel' => 'PRIMARIA',
            'match_keywords' => ['PRIMARIA', 'MATRICULA'],
            'data_start_row' => 10,
            'header_row_index' => 8,
            'subheader_row_index' => 9,
            'columns' => [
                'DRE' => 0, 'UGEL' => 1, 'DEPARTAMENTO' => 2, 'PROVINCIA' => 3, 'DISTRITO' => 4,
                'CENTRO_POBLADO' => 5, 'COD_MOD' => 6, 'ANEXO' => 7, 'NOMBRE_IE' => 8, 'NIVEL' => 9,
                'MODALIDAD' => 10, 'TIPO_IE' => 11, 'TOTAL_MATRICULADOS' => 12, 'MATRICULA_DEFINITIVA' => 14,
                'MATRICULA_EN_PROCESO' => 16, 'DNI_VALIDADO' => 19, 'DNI_SIN_VALIDAR' => 20, 'SIN_DNI' => 21,
                'TOTAL_GRADOS' => 22, 'TOTAL_SECCIONES' => 23, 'NOM_GENERADAS' => 24, 'NOM_APROBADAS' => 25,
                'NOM_RECTIFICAR' => 26, 'PR1H' => 27, 'PR1M' => 28, 'PR2H' => 29, 'PR2M' => 30,
                'PR3H' => 31, 'PR3M' => 32, 'PR4H' => 33, 'PR4M' => 34, 'PR5H' => 35, 'PR5M' => 36,
                'PR6H' => 37, 'PR6M' => 38,
            ],
        ]);

        $found = $service->findBestProfile(
            ['DRE', 'UGEL', 'Departamento', 'Provincia', 'Distrito', 'Centro Poblado', 'Cod. Mod.', 'Anexo', 'Nombre de IE', 'B0 - Secundaria'],
            ['', '', '', '', '', '', '', '', '', ''],
            'rptConsolMatriculaIePorAnioSec',
            'B0 - Secundaria',
            'SECUNDARIA'
        );

        $this->assertNotSame('SECUNDARIA', $profile['nivel']);
        $this->assertNull($found);

        @unlink($path);
    }
}
