<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                $status = match (true) {
                    $e instanceof ModelNotFoundException => 404,
                    $e instanceof AuthenticationException => 401,
                    $e instanceof AuthorizationException => 403,
                    $e instanceof ValidationException => 422,
                    $e instanceof HttpException => $e->getStatusCode(),
                    default => 500,
                };

                return response()->json(['error' => $e->getMessage()], $status);
            }

            if ($request->header('X-Inertia')) {
                $status = match (true) {
                    $e instanceof ModelNotFoundException => 404,
                    $e instanceof AuthenticationException => 401,
                    $e instanceof AuthorizationException => 403,
                    $e instanceof HttpException => $e->getStatusCode(),
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
