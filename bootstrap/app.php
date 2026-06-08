<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'extra-tasks',
            'extra-tasks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson()) {
                $status = match (true) {
                    $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                    $e instanceof \Illuminate\Auth\AuthenticationException => 401,
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                    $e instanceof \Illuminate\Validation\ValidationException => 422,
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => $e->getStatusCode(),
                    default => 500,
                };

                return response()->json(['error' => $e->getMessage()], $status);
            }

            if ($request->header('X-Inertia')) {
                $status = match (true) {
                    $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                    $e instanceof \Illuminate\Auth\AuthenticationException => 401,
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => $e->getStatusCode(),
                    default => null,
                };

                if ($status === 403) {
                    $back = $request->header('X-Inertia-Previous-URL') ?: url()->previous();
                    return redirect($back)->with('error', 'No tienes permisos para realizar esta acción.');
                }

                if ($status === 404) {
                    return redirect()->route('dashboard')->with('error', 'Recurso no encontrado.');
                }
            }
        });
    })->create();
