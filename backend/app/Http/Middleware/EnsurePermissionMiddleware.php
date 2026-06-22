<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'mensaje' => 'No tienes permisos suficientes para ejecutar esta accion.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
