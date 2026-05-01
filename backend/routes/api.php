<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return $request->user();
    });

    Route::prefix('matricula')->group(function () {
        Route::post('/preview', [MatriculaController::class, 'preview']);
        Route::post('/procesar', [MatriculaController::class, 'procesar']);
        Route::get('/descargar/{filename}', [MatriculaController::class, 'descargar']);
        Route::get('/descargar-pdf/{filename}', [MatriculaController::class, 'descargarPdf']);
        Route::get('/descargar-zip', [MatriculaController::class, 'descargarZip']);
    });
});



