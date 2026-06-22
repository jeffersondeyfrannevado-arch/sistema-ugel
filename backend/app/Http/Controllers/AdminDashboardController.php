<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use App\Services\AdminReportService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
        private readonly AdminReportService $reportService
    ) {}

    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'dashboard' => $this->dashboardService->build(
                $request->query('from'),
                $request->query('to')
            ),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:pdf,excel',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $dashboard = $this->dashboardService->build(
            $request->query('from'),
            $request->query('to')
        );

        if ($request->query('format') === 'excel') {
            $path = $this->reportService->buildExcel($dashboard);

            return response()->download($path)->deleteFileAfterSend(true);
        }

        [$content, $name] = $this->reportService->buildPdf($dashboard);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }
}
