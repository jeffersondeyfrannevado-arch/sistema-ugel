<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $logPath = storage_path('logs/laravel.log');
    $logDir = storage_path('logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $lines = file_exists($logPath) ? file($logPath) : [];
    $lastLines = array_slice($lines, -150);
    $logText = implode("", $lastLines);

    // Buscar el último código MFA registrado
    $latestCode = null;
    $latestEmail = null;
    if (preg_match_all('/MFA CODE para ([^:]+): \[ (\d+) \]/', $logText, $matches)) {
        $count = count($matches[0]);
        $latestEmail = $matches[1][$count - 1];
        $latestCode = $matches[2][$count - 1];
    }

    $latestMfaFile = storage_path('app/latest_mfa.json');
    if (file_exists($latestMfaFile)) {
        $saved = @json_decode(file_get_contents($latestMfaFile), true);
        if (!empty($saved['code'])) {
            $latestCode = $saved['code'];
            $latestEmail = $saved['email'] ?? $latestEmail;
        }
    }

    $output = "=====================================================\n";
    $output .= "★ SISTEMA UGEL PIURA - PANEL DE CÓDIGOS Y LOGS ★\n";
    $output .= "=====================================================\n";
    if ($latestCode) {
        $output .= "\n>>> ÚLTIMO CÓDIGO MFA GENERADO: [ $latestCode ] <<<\n";
        $output .= ">>> Usuario: $latestEmail <<<\n";
        $output .= ">>> Hora del servidor: " . now()->format('Y-m-d H:i:s') . " <<<\n\n";
    } else {
        $output .= "\nAún no se ha registrado ningún código MFA en esta sesión o el servicio acaba de iniciar.\n";
        $output .= "Haz clic en 'REENVIAR CÓDIGO MFA' en el sistema y recarga esta página.\n\n";
    }
    $output .= "=====================================================\n";
    $output .= "--- ÚLTIMAS 150 LÍNEAS DE LOGS DEL SISTEMA ---\n";
    $output .= "=====================================================\n\n";
    $output .= !empty($logText) ? $logText : "[ " . now() . " ] Sistema iniciado correctamente. Esperando acciones...\n";

    return response($output, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

Route::get('/view-logs', function () {
    return redirect('/');
});

