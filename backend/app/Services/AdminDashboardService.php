<?php

namespace App\Services;

use App\Models\AdminBackup;
use App\Models\AuditLog;
use App\Models\SystemContent;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    public function build(?string $from = null, ?string $to = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $auditBase = AuditLog::query()->whereBetween('created_at', [$start, $end]);

        return [
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'metrics' => [
                'usuarios_totales' => User::count(),
                'usuarios_activos' => User::where('is_active', true)->count(),
                'usuarios_bloqueados' => User::whereNotNull('locked_until')
                    ->where('locked_until', '>', now())
                    ->count(),
                'mfa_habilitado' => User::where('mfa_enabled', true)->count(),
                'administradores' => User::whereIn('role', ['super_admin', 'sub_admin', 'custom'])->count(),
                'contenidos_publicados' => SystemContent::where('status', 'published')->count(),
                'contenidos_marcados' => SystemContent::where('is_flagged', true)->count(),
                'respaldos_generados' => AdminBackup::count(),
                'eventos_auditoria' => (clone $auditBase)->count(),
                'intentos_fallidos' => (clone $auditBase)->where('action', 'auth.login.failed')->count(),
            ],
            'recent_actions' => AuditLog::query()
                ->with('actor:id,name,email')
                ->latest()
                ->limit(12)
                ->get(),
            'alerts' => [
                'bloqueos_recientes' => AuditLog::query()
                    ->where('action', 'auth.login.locked')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'contenido_marcado' => SystemContent::query()
                    ->where('is_flagged', true)
                    ->latest()
                    ->limit(5)
                    ->get(),
            ],
            'charts' => [
                'acciones_por_dia' => AuditLog::query()
                    ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                    ->whereBetween('created_at', [$start, $end])
                    ->groupByRaw('DATE(created_at)')
                    ->orderBy('fecha')
                    ->get(),
                'usuarios_por_rol' => User::query()
                    ->selectRaw('role, COUNT(*) as total')
                    ->groupBy('role')
                    ->orderBy('role')
                    ->get(),
            ],
        ];
    }
}
