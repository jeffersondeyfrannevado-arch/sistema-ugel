<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsurePermissionMiddleware;
use App\Services\SystemAlertService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'permission' => EnsurePermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof QueryException || $e instanceof \PDOException) {
                app(SystemAlertService::class)->reportException($e, $request);

                return response()->json([
                    'success' => false,
                    'mensaje' => 'La base de datos no esta disponible. Verifica que MySQL este encendido y que la conexion configurada en backend/.env sea correcta.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            app(SystemAlertService::class)->reportException($e, $request);

            return response()->json([
                'success' => false,
                'mensaje' => app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : 'Ocurrio un error interno en el servidor.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
