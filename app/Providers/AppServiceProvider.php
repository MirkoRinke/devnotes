<?php

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\CommentModerationService;
use App\Services\CommentRelationService;
use App\Services\ExternalSourceService;
use App\Services\GuestAccountService;
use App\Services\HistoryService;
use App\Services\ModerationService;
use App\Services\PostRelationService;
use App\Services\SnapshotService;
use App\Services\UserModerationService;
use App\Services\UserRelationService;

use App\Traits\ApiResponses;


class AppServiceProvider extends ServiceProvider {

    /**
     * The traits used in the ServiceProvider
     */
    use ApiResponses;

    /**
     * Register any application services.
     */
    public function register(): void {
        $this->app->singleton(CommentModerationService::class);
        $this->app->singleton(CommentRelationService::class);
        $this->app->singleton(ExternalSourceService::class);
        $this->app->singleton(GuestAccountService::class);
        $this->app->singleton(HistoryService::class);
        $this->app->singleton(ModerationService::class);
        $this->app->singleton(PostRelationService::class);
        $this->app->singleton(SnapshotService::class);
        $this->app->singleton(UserModerationService::class);
        $this->app->singleton(UserRelationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        if (env('QUERY_LOGGING_ENABLED', false)) {
            DB::enableQueryLog();
        }


        /**
         * Rate limiting for the API requests.
         */
        //!TODO Delete the out commented code after testing
        RateLimiter::for('api', function (Request $request) {
            // Get the user's IP address from the request header
            $userIp = $request->header('X-Forwarded-For') ?: $request->ip();

            // The key is the user id or the IP address of the user    
            $key = 'api:' . ($request->user()?->id ?: $userIp);

            $maxAttempts = 240;

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

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return $this->errorResponse('Too many requests', 'TOO_MANY_REQUESTS', 429);
            }

            return Limit::perMinute($maxAttempts)->by($key);
        });

        // Customize the email verification URL to point to the frontend
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = config('auth.frontend.url', 'http://localhost:4200');
            $verifyUrl = config('auth.frontend.verify_email_url', '/auth/verify-email');

            $params = http_build_query([
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);

            return $frontendUrl . $verifyUrl . '?' . $params;
        });


        /**
         * Customizing the password reset URL
         */
        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            $frontendUrl = config('auth.frontend.url', 'http://localhost:4200');
            $resetUrl = config('auth.frontend.reset_password_url', '/auth/reset-password');
            return $frontendUrl . $resetUrl . '?token=' . $token . '&email=' . urlencode($notifiable->email);
        });
    }
}
