<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin() || !$user->hasPermission('admin.dashboard.view')) {
            return response()->json([
                'success' => false,
                'mensaje' => 'No tienes permisos para acceder a este recurso.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
