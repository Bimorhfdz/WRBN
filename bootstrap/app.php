<?php

use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\EnsureUserIsAuthenticated;
use App\Http\Middleware\RequireAdminRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The legacy frontend sends JSON with no CSRF token; protection relies on
        // the SameSite=Lax session cookie plus same-origin requests (see README).
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->alias([
            'auth.user' => EnsureUserIsAuthenticated::class,
            'admin.role' => RequireAdminRole::class,
            'auth.admin' => EnsureAdminIsAuthenticated::class,
            'security.headers' => SecurityHeaders::class,
        ]);

        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                $errors = collect($exception->errors())->flatten()->filter();
                $message = $errors->first() ?: $exception->getMessage();

                return response()->json(['message' => $message], 400);
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = trim($exception->getMessage());

                return response()->json(['message' => $message !== '' ? $message : 'Authentication diperlukan.'], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = method_exists($exception, 'getMessage') ? trim($exception->getMessage()) : '';

                return response()->json(['message' => $message !== '' ? $message : 'Endpoint tidak ditemukan.'], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = trim($exception->getMessage());

                return response()->json(['message' => $message !== '' ? $message : 'Terlalu banyak percobaan. Coba lagi nanti.'], 429);
            }
        });
    })->create();
