<?php


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\EnsureEmailIsVerifiedApi;
use App\Http\Middleware\EnsurePrivacyPolicyAccepted;
use App\Http\Middleware\EnsureTermsOfServiceAccepted;
use App\Http\Middleware\ValidateApiKey;
use App\Http\Middleware\VerifyDeviceFingerprint;

use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;

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
            'terms-of-service-accepted' => EnsureTermsOfServiceAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * Handle exceptions for a requested route that does not exist.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Route not found',
                    'code' => 404,
                    'errors' => 'ROUTE_NOT_FOUND'
                ], 404);
            }
        });

        /**
         * Handle exceptions for a named route that does not exist.
         */
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Route name not found',
                    'code' => 500,
                    'errors' => 'ROUTE_NAME_NOT_FOUND'
                ], 500);
            }
        });

        /**
         * Handle exceptions for unauthenticated access to protected routes.
         */
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'code' => 401,
                    'errors' => 'UNAUTHORIZED'
                ], 401);
            }
        });
    })
    ->create();
