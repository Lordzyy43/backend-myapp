<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            // future:
            // 'is_owner' => \App\Http\Middleware\IsOwner::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('booking:expire')->everyMinute();
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
    ])

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (Throwable $e, $request) {

            // 🔥 TRACE ID (untuk debugging production)
            $traceId = uniqid('ERR_');

            Log::error("[$traceId] " . $e->getMessage(), [
                'exception' => $e
            ]);

            /**
             * 🔹 VALIDATION ERROR (422)
             */
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $e->errors(),
                    'trace_id' => $traceId,
                ], 422);
            }

            /**
             * 🔹 AUTHENTICATION (401)
             */
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'errors' => [],
                    'trace_id' => $traceId,
                ], 401);
            }

            /**
             * 🔹 MODEL NOT FOUND (404)
             */
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'errors' => [],
                    'trace_id' => $traceId,
                ], 404);
            }

            /**
             * 🔹 HTTP EXCEPTION (403, dll)
             */
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan',
                    'errors' => [],
                    'trace_id' => $traceId,
                ], $e->getStatusCode());
            }

            /**
             * 🔥 DEFAULT ERROR (500)
             */
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'errors' => [],
                'trace_id' => $traceId,
            ], 500);
        });
    })
    ->create();
