<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaintenanceTokenMiddleware
{
    /**
     * Valida que la peticion incluya el token secreto de mantenimiento
     * configurado en ADMIN_MAINTENANCE_TOKEN.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) (config('admin.maintenance_token') ?: env('ADMIN_MAINTENANCE_TOKEN', ''));

        if (empty($configuredToken)) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Acceso denegado: El token secreto de mantenimiento (ADMIN_MAINTENANCE_TOKEN) no esta configurado en el servidor.',
            ], Response::HTTP_FORBIDDEN);
        }

        $providedToken = $request->header('X-Maintenance-Token')
            ?? $request->header('X-Secret-Token')
            ?? $request->bearerToken()
            ?? $request->query('secret')
            ?? $request->query('token')
            ?? $request->input('secret')
            ?? $request->input('token');

        if (!is_string($providedToken) || empty($providedToken) || !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Acceso denegado: Token secreto de mantenimiento invalido o no suministrado.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
