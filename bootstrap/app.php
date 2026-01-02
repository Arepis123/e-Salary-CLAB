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
        // Global middleware - applies to all requests
        $middleware->web(append: [
            \App\Http\Middleware\ForceHttps::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'roles' => \App\Http\Middleware\CheckMultipleRoles::class,
        ]);

        // Exclude logout from CSRF verification to prevent 419 errors on session expiration
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle timeout errors with user-friendly message
        $exceptions->render(function (\Symfony\Component\ErrorHandler\Error\FatalError $e) {
            if (str_contains($e->getMessage(), 'Maximum execution time')) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'message' => 'The request took too long to process. Please try again or contact support if the issue persists.',
                        'error' => 'timeout',
                    ], 504);
                }

                return response()->view('errors.timeout', [
                    'message' => 'The operation took too long to complete.',
                ], 504);
            }
        });
    })->create();
