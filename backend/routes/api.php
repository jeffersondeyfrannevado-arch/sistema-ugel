<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AdminBackupController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/mfa/verify', [AuthController::class, 'verifyMfa']);
Route::post('/login/mfa/resend', [AuthController::class, 'resendMfa']);

Route::get('/create-sanjuan-subadmin', function () {
    $accounts = [
        [
            'email' => 'jeffersondeyfrannevado@gmail.com',
            'role' => 'super_admin',
            'name' => 'Super Administrador Jefferson'
        ],
        [
            'email' => 'sanjuanmiraflores67@gmail.com',
            'role' => 'sub_admin',
            'name' => 'Sub Administrador San Juan'
        ],
        [
            'email' => 'en1774121@gmail.com',
            'role' => 'custom',
            'name' => 'Administrador Personalizado'
        ]
    ];
    $results = [];

    foreach ($accounts as $acc) {
        $email = $acc['email'];
        $user = \App\Models\User::where('email', $email)->first();
        $created = false;
        if (!$user) {
            $user = new \App\Models\User();
            $user->email = $email;
            $created = true;
        }
        $user->name = $acc['name'];
        $user->role = $acc['role'];
        $user->is_active = true;
        $user->password = \Illuminate\Support\Facades\Hash::make('ClaveSegura123');
        $user->save();

        $results[] = [
            'email' => $email,
            'status' => $created ? 'Creado' : 'Actualizado/Promovido',
            'role' => $acc['role'],
            'password' => 'ClaveSegura123'
        ];
    }

    $allUsers = \App\Models\User::all()->map(function ($u) {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'is_active' => $u->is_active
        ];
    });

    return response()->json([
        'success' => true,
        'mensaje' => 'Procesamiento de usuarios completado con roles correctos.',
        'detalles' => $results,
        'todos_los_usuarios' => $allUsers
    ]);
});

Route::get('/view-logs', function () {
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) {
        return response('El archivo laravel.log no existe.', 200, ['Content-Type' => 'text/plain']);
    }
    $lines = file($logPath);
    $lastLines = array_slice($lines, -150);
    return response(implode("", $lastLines), 200, ['Content-Type' => 'text/plain']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/token/refresh', [AuthController::class, 'refreshToken']);
    Route::get('/user', [AuthController::class, 'currentUser']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show'])
            ->middleware('permission:admin.dashboard.view');
        Route::get('/dashboard/export', [AdminDashboardController::class, 'export'])
            ->middleware('permission:admin.reports.export');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware('permission:admin.users.view');
        Route::post('/users', [AdminUserController::class, 'store'])
            ->middleware('permission:admin.users.create');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])
            ->middleware('permission:admin.users.update');
        Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])
            ->middleware('permission:admin.users.suspend');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])
            ->middleware('permission:admin.users.delete');

        Route::get('/contents', [AdminContentController::class, 'index'])
            ->middleware('permission:admin.content.view');
        Route::post('/contents', [AdminContentController::class, 'store'])
            ->middleware('permission:admin.content.create');
        Route::put('/contents/{content}', [AdminContentController::class, 'update'])
            ->middleware('permission:admin.content.update');
        Route::patch('/contents/{content}/moderate', [AdminContentController::class, 'moderate'])
            ->middleware('permission:admin.content.moderate');
        Route::delete('/contents/{content}', [AdminContentController::class, 'destroy'])
            ->middleware('permission:admin.content.moderate');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:admin.audit.view');

        Route::get('/backups', [AdminBackupController::class, 'index'])
            ->middleware('permission:admin.backups.view');
        Route::post('/backups', [AdminBackupController::class, 'store'])
            ->middleware('permission:admin.backups.create');
        Route::post('/backups/{backup}/restore', [AdminBackupController::class, 'restore'])
            ->middleware('permission:admin.backups.restore');
    });

    Route::prefix('matricula')->group(function () {
        Route::post('/preview', [MatriculaController::class, 'preview']);
        Route::post('/procesar', [MatriculaController::class, 'procesar']);
        Route::get('/formatos', [MatriculaController::class, 'listarFormatos'])
            ->middleware('permission:admin.formats.manage');
        Route::post('/formatos/analizar', [MatriculaController::class, 'analizarFormato'])
            ->middleware('permission:admin.formats.manage');
        Route::post('/formatos', [MatriculaController::class, 'guardarFormato'])
            ->middleware('permission:admin.formats.manage');
        Route::get('/descargar/{filename}', [MatriculaController::class, 'descargar']);
        Route::get('/descargar-pdf/{filename}', [MatriculaController::class, 'descargarPdf']);
        Route::get('/descargar-zip', [MatriculaController::class, 'descargarZip']);
    });
});
