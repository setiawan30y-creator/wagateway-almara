<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogApiRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/*
| ALMARA Gateway WA — @2026-Alifiandra
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'api.log' => LogApiRequest::class,
            'active' => EnsureUserIsActive::class,
            'feature' => \App\Http\Middleware\RequirePlanFeature::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Envelope error yang konsisten untuk semua endpoint /api/*
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('VALIDATION_ERROR', 'Request tidak valid.', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('RATE_LIMITED', 'Terlalu banyak request. Coba lagi nanti.', 429, null, $e->getHeaders());
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    409 => 'CONFLICT',
                    default => 'HTTP_ERROR',
                };

                return ApiResponse::error($code, $status === 404 ? 'Resource tidak ditemukan.' : ($e->getMessage() ?: 'Terjadi kesalahan.'), $status);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! config('app.debug')) {
                return ApiResponse::error('SERVER_ERROR', 'Terjadi kesalahan pada server.', 500);
            }
        });
    })->create();
