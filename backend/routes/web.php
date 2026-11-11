<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $logPath = storage_path('logs/laravel.log');
    $logDir = storage_path('logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logText = '';
    if (file_exists($logPath)) {
        $size = @filesize($logPath) ?: 0;
        // Rotar/truncar automáticamente si el log supera 5 MB para evitar consumo en contenedor Render
        if ($size > 5 * 1024 * 1024) {
            $fp = @fopen($logPath, 'rb');
            if ($fp) {
                @fseek($fp, -262144, SEEK_END);
                $tail = @fread($fp, 262144) ?: '';
                @fclose($fp);
                @file_put_contents($logPath, $tail);
                $size = strlen($tail);
            }
        }
        if ($size > 0) {
            $readBytes = min($size, 32768);
            $fp = @fopen($logPath, 'rb');
            if ($fp) {
                @fseek($fp, -$readBytes, SEEK_END);
                $chunk = @fread($fp, $readBytes) ?: '';
                @fclose($fp);
                $lines = explode("\n", $chunk);
                $lastLines = array_slice($lines, -150);
                $logText = implode("\n", $lastLines);
            }
        }
    }

    $latestCode = null;
    $latestEmail = null;
    $latestTime = null;

    // 1. Intentar desde Cache (PostgreSQL Supabase)
    try {
        $cached = Cache::get('latest_mfa_code');
        if (is_array($cached) && !empty($cached['code'])) {
            $latestCode = (string) $cached['code'];
            $latestEmail = $cached['email'] ?? null;
            $latestTime = $cached['time'] ?? null;
        }
    } catch (\Throwable $e) {
    }

    // 2. Intentar desde storage/app/latest_mfa.json
    if (!$latestCode) {
        $latestMfaFile = storage_path('app/latest_mfa.json');
        if (file_exists($latestMfaFile)) {
            $saved = @json_decode(file_get_contents($latestMfaFile), true);
            if (!empty($saved['code'])) {
                $latestCode = (string) $saved['code'];
                $latestEmail = $saved['email'] ?? $latestEmail;
                $latestTime = $saved['time'] ?? $latestTime;
            }
        }
    }

    // 3. Intentar extraer del log mediante regex
    if (!$latestCode && !empty($logText)) {
        if (preg_match_all('/MFA CODE para ([^:]+): \[ (\d+) \]/', $logText, $matches)) {
            $count = count($matches[0]);
            $latestEmail = $matches[1][$count - 1];
            $latestCode = $matches[2][$count - 1];
        }
    }

    // Si la petición pide JSON
    if ($request->wantsJson()) {
        return response()->json([
            'status' => 'online',
            'latest_mfa' => [
                'code' => $latestCode,
                'email' => $latestEmail,
                'time' => $latestTime,
            ],
            'server_time' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    // Si es curl o texto plano
    $isCli = str_contains((string) $request->userAgent(), 'curl')
        || str_contains((string) $request->header('Accept'), 'text/plain');

    if ($isCli) {
        $output = "=====================================================\n";
        $output .= "★ SISTEMA UGEL PIURA - PANEL DE CÓDIGOS Y LOGS ★\n";
        $output .= "=====================================================\n";
        if ($latestCode) {
            $output .= "\n>>> ÚLTIMO CÓDIGO MFA GENERADO: [ $latestCode ] <<<\n";
            $output .= ">>> Usuario: $latestEmail <<<\n";
            $output .= ">>> Hora registro: " . ($latestTime ?: now()->format('Y-m-d H:i:s')) . " <<<\n\n";
        } else {
            $output .= "\nAún no se ha registrado ningún código MFA en esta sesión o el servicio acaba de iniciar.\n";
            $output .= "Haz clic en 'REENVIAR CÓDIGO MFA' en el sistema y recarga esta página.\n\n";
        }
        $output .= "=====================================================\n";
        $output .= "--- ÚLTIMAS 150 LÍNEAS DE LOGS DEL SISTEMA ---\n";
        $output .= "=====================================================\n\n";
        $output .= !empty($logText) ? $logText : "[ " . now() . " ] Esperando acciones...\n";

        return response($output, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    // Para navegadores web: HTML moderno con botón de copiar y auto-actualización
    $serverTime = now()->format('Y-m-d H:i:s');
    $safeEmail = htmlspecialchars($latestEmail ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars($latestTime ?? $serverTime, ENT_QUOTES, 'UTF-8');
    $safeLog = htmlspecialchars($logText ?: "[ " . now() . " ] Esperando nuevas solicitudes...", ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Código MFA - Sistema UGEL</title>
    <meta http-equiv="refresh" content="5">
    <style>
        :root {
            --bg-color: #0b1120;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-green: #10b981;
            --accent-blue: #38bdf8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
        }
        .container {
            width: 100%;
            max-width: 850px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .header {
            text-align: center;
            padding: 12px;
        }
        .header h1 {
            font-size: 1.6rem;
            color: var(--accent-blue);
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .code-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: #0f172a;
            border: 2px dashed var(--accent-green);
            border-radius: 10px;
            padding: 16px 28px;
            margin: 16px 0;
        }
        .code-digits {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: 6px;
            color: #34d399;
            text-shadow: 0 0 15px rgba(52, 211, 153, 0.35);
        }
        .empty-state {
            padding: 20px;
            color: var(--text-muted);
        }
        .empty-state .spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid rgba(56, 189, 248, 0.2);
            border-top-color: var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .meta-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .tag {
            background: rgba(255, 255, 255, 0.05);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .tag strong { color: var(--text-main); }
        .btn-row {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 14px;
        }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-copy { background: #059669; }
        .btn-copy:hover { background: #047857; }
        .btn-secondary { background: #334155; }
        .btn-secondary:hover { background: #475569; }
        .copied-toast {
            display: none;
            color: #34d399;
            font-size: 0.85rem;
            font-weight: bold;
            margin-top: 8px;
        }
        .terminal-card {
            background-color: #050811;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            text-align: left;
        }
        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: var(--text-muted);
            border-bottom: 1px solid #1e293b;
            padding-bottom: 8px;
        }
        .terminal-logs {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.82rem;
            color: #94a3b8;
            max-height: 280px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.45;
        }
        .auto-refresh-notice {
            font-size: 0.8rem;
            color: #64748b;
            text-align: center;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Panel de Verificación - UGEL Piura</h1>
            <p>Servicio de consulta en vivo de códigos de seguridad MFA</p>
        </div>

        <div class="card">
HTML;

    if ($latestCode) {
        $html .= <<<HTML
            <h2 style="font-size: 1.15rem; color: #e2e8f0; font-weight: 600;">CÓDIGO MFA ACTIVO</h2>
            <div class="code-container">
                <span class="code-digits" id="codeText">{$latestCode}</span>
            </div>
            <div class="btn-row">
                <button class="btn btn-copy" onclick="copyCode()">📋 Copiar Código</button>
                <button class="btn btn-secondary" onclick="window.location.reload()">🔄 Actualizar</button>
                <a href="https://frontend-jumaira.vercel.app" target="_blank" class="btn">🚀 Ir a la Aplicación</a>
            </div>
            <div id="copiedToast" class="copied-toast">¡Código copiado al portapapeles! Pégalo en tu pantalla de login.</div>
            <div class="meta-tags">
                <span class="tag">Usuario: <strong>{$safeEmail}</strong></span>
                <span class="tag">Generado: <strong>{$safeTime}</strong></span>
                <span class="tag">Vigencia: <strong>10 minutos</strong></span>
            </div>
HTML;
    } else {
        $html .= <<<HTML
            <div class="empty-state">
                <div class="spinner"></div>
                <h3 style="color: #cbd5e1; margin-bottom: 8px;">Esperando código de verificación...</h3>
                <p style="font-size: 0.95rem; line-height: 1.5; max-width: 600px; margin: 0 auto 16px;">
                    Aún no se ha solicitado un código en esta sesión.<br>
                    Ve a <a href="https://frontend-jumaira.vercel.app" target="_blank" style="color: #38bdf8; text-decoration: underline;">https://frontend-jumaira.vercel.app</a>, inicia sesión o pulsa <strong>"Reenviar Código MFA"</strong>.
                </p>
                <div class="btn-row">
                    <button class="btn btn-secondary" onclick="window.location.reload()">🔄 Actualizar Ahora</button>
                    <a href="https://frontend-jumaira.vercel.app" target="_blank" class="btn">🚀 Ir al Login</a>
                </div>
            </div>
HTML;
    }

    $html .= <<<HTML
            <p class="auto-refresh-notice">Esta página se actualiza automáticamente cada 5 segundos.</p>
        </div>

        <div class="terminal-card">
            <div class="terminal-header">
                <span>Registros recientes del servidor (laravel.log)</span>
                <span>Hora del servidor: {$serverTime}</span>
            </div>
            <div class="terminal-logs">{$safeLog}</div>
        </div>
    </div>

    <script>
        function copyCode() {
            var code = document.getElementById('codeText').innerText.trim();
            navigator.clipboard.writeText(code).then(function() {
                var toast = document.getElementById('copiedToast');
                if (toast) {
                    toast.style.display = 'block';
                    setTimeout(function() { toast.style.display = 'none'; }, 3000);
                }
            });
        }
    </script>
</body>
</html>
HTML;

    return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
});

Route::get('/view-logs', function () {
    return redirect('/');
});
