<?php

namespace App\Providers;

use App\Services\CommentModerationService;
use App\Services\externalSourceService;
use App\Services\ModerationService;
use App\Services\UserModerationService;
use Illuminate\Support\ServiceProvider;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

class AppServiceProvider extends ServiceProvider {
    // Import the ApiResponses trait here to use it in the RateLimiter
    use ApiResponses;

    /**
     * Register any application services.
     */
    public function register(): void {
        $this->app->singleton(UserModerationService::class);
        $this->app->singleton(ModerationService::class);
        $this->app->singleton(CommentModerationService::class);
        $this->app->singleton(externalSourceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        /**
         * Rate limiting for the API requests.
         */
        //!TODO Delete the out commented code after testing
        RateLimiter::for('api', function (Request $request) {
            // The key is the user id or the IP address of the user
            $key = 'api:' . ($request->user()?->id ?: $request->ip());
            // The maximum number of attempts allowed in a minute
            $maxAttempts = 120;

            // $beforeAttempts = RateLimiter::attempts($key);
            // $beforeRemaining = RateLimiter::remaining($key, $maxAttempts);

            // The hit method increments the number of attempts by 1
            RateLimiter::hit($key);

            // $afterAttempts = RateLimiter::attempts($key);
            // $afterRemaining = RateLimiter::remaining($key, $maxAttempts);

            // dd([
            //     'key' => $key,
            //     'before_attempts' => $beforeAttempts,
            //     'before_remaining' => $beforeRemaining,
            //     'after_attempts' => $afterAttempts,
            //     'after_remaining' => $afterRemaining,
            //     'endpoint' => $request->path(),
            //     'method' => $request->method()
            // ]);

            // If the number of attempts exceeds the maximum allowed attempts
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return $this->errorResponse('Too many requests', 'TOO_MANY_REQUESTS', 429);
            }

            // The perMinute method is used to limit the number of requests per minute
            return Limit::perMinute($maxAttempts)->by($key);
        });
    }
}
