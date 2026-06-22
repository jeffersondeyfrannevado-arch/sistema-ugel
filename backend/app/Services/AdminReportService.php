<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminReportService
{
    public function buildExcel(array $dashboard): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dashboard Admin');

        $sheet->setCellValue('A1', 'Reporte Administrativo');
        $sheet->setCellValue('A2', 'Desde');
        $sheet->setCellValue('B2', $dashboard['range']['from'] ?? '');
        $sheet->setCellValue('A3', 'Hasta');
        $sheet->setCellValue('B3', $dashboard['range']['to'] ?? '');

        $row = 5;
        $sheet->setCellValue("A{$row}", 'Metricas');
        $row++;

        foreach ($dashboard['metrics'] ?? [] as $label => $value) {
            $sheet->setCellValue("A{$row}", str_replace('_', ' ', $label));
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Acciones recientes');
        $row++;
        $sheet->setCellValue("A{$row}", 'Fecha');
        $sheet->setCellValue("B{$row}", 'Accion');
        $sheet->setCellValue("C{$row}", 'Actor');
        $row++;

        foreach ($dashboard['recent_actions'] ?? [] as $item) {
            $sheet->setCellValue("A{$row}", (string) $item['created_at']);
            $sheet->setCellValue("B{$row}", (string) $item['action']);
            $sheet->setCellValue("C{$row}", (string) ($item['actor']['email'] ?? 'Sistema'));
            $row++;
        }

        $path = storage_path('app/admin_dashboard_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function buildPdf(array $dashboard): array
    {
        $lines = [
            'REPORTE ADMINISTRATIVO',
            'Desde: ' . ($dashboard['range']['from'] ?? ''),
            'Hasta: ' . ($dashboard['range']['to'] ?? ''),
            '',
            'METRICAS',
        ];

        foreach ($dashboard['metrics'] ?? [] as $label => $value) {
            $lines[] = strtoupper(str_replace('_', ' ', $label)) . ': ' . $value;
        }

        $lines[] = '';
        $lines[] = 'ACCIONES RECIENTES';

        foreach ($dashboard['recent_actions'] ?? [] as $item) {
            $lines[] = sprintf(
                '%s | %s | %s',
                $item['created_at'] ?? '',
                $item['action'] ?? '',
                $item['actor']['email'] ?? 'Sistema'
            );
        }

        return [$this->buildSimplePdf($lines), 'admin_dashboard_' . now()->format('Ymd_His') . '.pdf'];
    }

    private function buildSimplePdf(array $lines): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Count 1 /Kids [3 0 R] >>";

        $content = "BT\n/F1 10 Tf\n36 800 Td\n14 TL\n";
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= '(' . $escaped . ") Tj\nT*\n";
        }
        $content .= "ET";

        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Courier >> >> >> /Contents 4 0 R >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

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
