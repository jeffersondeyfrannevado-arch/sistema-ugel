<?php

namespace App\Services;

use App\Models\AdminBackup;
use App\Models\AuditLog;
use App\Models\SystemContent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardService
{
    public function build(?string $from = null, ?string $to = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $auditBase = AuditLog::query()->whereBetween('created_at', [$start, $end]);

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $trueVal = $isPgsql ? DB::raw('true') : true;

        try {
            $usuariosTotales = User::count();
            $usuariosActivos = User::where('is_active', $trueVal)->count();
            $usuariosBloqueados = User::whereNotNull('locked_until')->where('locked_until', '>', now())->count();
            $mfaHabilitado = User::where('mfa_enabled', $trueVal)->count();
            $administradores = User::whereIn('role', ['super_admin', 'sub_admin', 'custom'])->count();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric users error: ' . $e->getMessage());
            $usuariosTotales = 0; $usuariosActivos = 0; $usuariosBloqueados = 0; $mfaHabilitado = 0; $administradores = 0;
        }

        try {
            $contenidosPublicados = SystemContent::where('status', 'published')->count();
            $contenidosMarcados = SystemContent::where('is_flagged', $trueVal)->count();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric contents error: ' . $e->getMessage());
            $contenidosPublicados = 0; $contenidosMarcados = 0;
        }

        try {
            $respaldosGenerados = AdminBackup::count();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric backups error: ' . $e->getMessage());
            $respaldosGenerados = 0;
        }

        try {
            $eventosAuditoria = (clone $auditBase)->count();
            $intentosFallidos = (clone $auditBase)->where('action', 'auth.login.failed')->count();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric audit error: ' . $e->getMessage());
            $eventosAuditoria = 0; $intentosFallidos = 0;
        }

        try {
            $recentActions = AuditLog::query()
                ->with('actor:id,name,email')
                ->latest()
                ->limit(12)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Dashboard recent_actions error: ' . $e->getMessage());
            $recentActions = collect();
        }

        try {
            $bloqueosRecientes = AuditLog::query()
                ->where('action', 'auth.login.locked')
                ->latest()
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            $bloqueosRecientes = collect();
        }

        try {
            $contenidoMarcado = SystemContent::query()
                ->where('is_flagged', $trueVal)
                ->latest()
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            $contenidoMarcado = collect();
        }

        // Acciones por día compatibles con PostgreSQL, SQLite y MySQL
        $accionesPorDia = collect();
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $dateExpr = "strftime('%Y-%m-%d', created_at)";
            } elseif ($driver === 'pgsql') {
                $dateExpr = "CAST(created_at AS DATE)";
            } else {
                $dateExpr = "DATE(created_at)";
            }

            $accionesPorDia = AuditLog::query()
                ->selectRaw("{$dateExpr} as fecha, COUNT(*) as total")
                ->whereBetween('created_at', [$start, $end])
                ->groupByRaw($dateExpr)
                ->orderByRaw($dateExpr)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Dashboard charts acciones error: ' . $e->getMessage());
            try {
                $accionesPorDia = AuditLog::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->get(['created_at'])
                    ->groupBy(fn ($item) => $item->created_at?->format('Y-m-d') ?? 'N/A')
                    ->map(fn ($group, $date) => ['fecha' => (string) $date, 'total' => $group->count()])
                    ->values();
            } catch (\Throwable $e2) {
                $accionesPorDia = collect();
            }
        }

        // Usuarios por rol
        $usuariosPorRol = collect();
        try {
            $usuariosPorRol = User::query()
                ->selectRaw('role, COUNT(*) as total')
                ->groupBy('role')
                ->orderBy('role')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Dashboard charts roles error: ' . $e->getMessage());
            try {
                $usuariosPorRol = User::all(['role'])
                    ->groupBy('role')
                    ->map(fn ($group, $role) => ['role' => (string) $role, 'total' => $group->count()])
                    ->values();
            } catch (\Throwable $e2) {
                $usuariosPorRol = collect();
            }
        }

        return [
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'metrics' => [
                'usuarios_totales' => $usuariosTotales,
                'usuarios_activos' => $usuariosActivos,
                'usuarios_bloqueados' => $usuariosBloqueados,
                'mfa_habilitado' => $mfaHabilitado,
                'administradores' => $administradores,
                'contenidos_publicados' => $contenidosPublicados,
                'contenidos_marcados' => $contenidosMarcados,
                'respaldos_generados' => $respaldosGenerados,
                'eventos_auditoria' => $eventosAuditoria,
                'intentos_fallidos' => $intentosFallidos,
            ],
            'recent_actions' => $recentActions,
            'alerts' => [
                'bloqueos_recientes' => $bloqueosRecientes,
                'contenido_marcado' => $contenidoMarcado,
            ],
            'charts' => [
                'acciones_por_dia' => $accionesPorDia,
                'usuarios_por_rol' => $usuariosPorRol,
            ],
        ];
    }
}
