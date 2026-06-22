<?php

namespace App\Http\Controllers;

use App\Models\AdminBackup;
use App\Services\AdminBackupService;
use Illuminate\Http\Request;

class AdminBackupController extends Controller
{
    public function __construct(
        private readonly AdminBackupService $backupService
    ) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'respaldos' => AdminBackup::query()->latest()->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'success' => true,
            'respaldo' => $this->backupService->safeCreateBackup($request->user()),
        ], 201);
    }

    public function restore(Request $request, AdminBackup $backup)
    {
        $this->backupService->restoreBackup($backup, $request->user());

        return response()->json([
            'success' => true,
            'mensaje' => 'Respaldo restaurado correctamente. Se revocaron las sesiones activas.',
        ]);
    }
}
