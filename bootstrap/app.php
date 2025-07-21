<?php


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\EnsureEmailIsVerifiedApi;
use App\Http\Middleware\EnsurePrivacyPolicyAccepted;
use App\Http\Middleware\ValidateApiKey;
use App\Http\Middleware\VerifyDeviceFingerprint;

use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /**
         * Register the middleware aliases for the application
         */
        $middleware->alias([
            'email-verified' => EnsureEmailIsVerifiedApi::class,
            'api-key' => ValidateApiKey::class,
            'device-fingerprint' => VerifyDeviceFingerprint::class,
            'privacy-policy-accepted' => EnsurePrivacyPolicyAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * Handle exceptions for route not found
         */
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if (!$request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'code' => 404,
                    'errors' => 'UNAUTHORIZED'
                ], 404);
            }
        });
    })
    ->create();
