<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CriticalTermController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\UserFavoriteController;
use App\Http\Controllers\Api\ForbiddenNameController;
use App\Http\Controllers\Api\PostAllowedValueController;
use App\Http\Controllers\Api\UserFollowerController;
use App\Http\Controllers\Api\UserLikeController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\UserReportController;
use App\Http\Controllers\Api\CronjobController;
use App\Http\Controllers\Api\UserStatsController;

/**
 * API Route Security Middleware
 * -----------------------------
 * All routes within this group are protected by:
 * 
 * 1. api-key: Ensures a valid API key is provided
 *    - Requires API key in 'X-API-Key' header or 'api_key' query parameter
 *    - Validates against API keys stored in database
 *    - Updates the 'last_used_at' timestamp on access
 * 
 * 2. throttle:api: Implements rate limiting
 *    - Based on user ID or client IP address
 *    - Uses X-Forwarded-For header for proxy requests
 *    - Limit: 120 requests per minute
 * 
 * 3. auth:sanctum: Authenticates users using Sanctum
 *    - Requires a valid Bearer token in the Authorization header
 *    - Validates against the 'users' table in the database
 *    - Ensures the user is authenticated
 * 
 * 4. privacy-policy-accepted: Checks if the user has accepted the current privacy policy
 *    - Verifies the 'privacy_policy_accepted_at' field on the user record
 *    - Compares the acceptance date with the current policy date (from .env or default)
 *    - Deletes the current token and returns an error if acceptance is missing or outdated
 *    - User must accept the latest privacy policy to continue using protected endpoints
 *
 * 5. terms-of-service-accepted: Checks if the user has accepted the current terms of service
 *    - Verifies the 'terms_of_service_accepted_at' field on the user record
 *    - Compares the acceptance date with the current terms date (from .env or default)
 *    - Deletes the current token and returns an error if acceptance is missing or outdated
 *
 * 6. device-fingerprint: Validates device identity
 *    - Requires matching device fingerprint in 'X-Device-Fingerprint' header
 *    - Compares against fingerprint stored during token creation
 *    - Automatically revokes tokens on fingerprint mismatch
 *    - Prevents token use on unauthorized devices
 * 
 * 7. email-verified: Ensures the user's email is verified
 *    - Checks if the user has a verified email address
 *    - Returns a 403 error if the email is not verified
 * 
 * Frontend-proxy implementation requires:
 *    - X-API-Key: The API key for backend access
 *    - X-Forwarded-For: The original client IP address
 *    - Authorization: Bearer token for authenticated user requests
 */
Route::middleware(['api-key', 'throttle:api'])->group(function () {

    /**
     * Route for registration
     */
    Route::post('/register', [RegisterController::class, 'register']);

    /**
     * Route for email verification
     */
    Route::post('/email/verify', [RegisterController::class, 'verifyEmail']);

    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint'])->group(function () {
        Route::post('/email/resend-verification-email', [RegisterController::class, 'resendVerificationEmail']);
    });

    /**
     * Route for admin sending verification email
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::post('/email/admin-send-verification-email', [RegisterController::class, 'adminSendVerificationEmail']);
    });

    /**
     * Route for login
     */
    Route::post('/login', [AuthController::class, 'login']);

    /**
     * Route for logout and tokens
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/tokens', [AuthController::class, 'getUserTokens']);
        Route::delete('/tokens/{id}', [AuthController::class, 'revokeToken']);
        Route::delete('/tokens', [AuthController::class, 'revokeAllTokens']);
    });

    /**
     * Route for password reset
     */
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    /**
     * Route for ban and unban users
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::post('/users/{id}/ban', [UserController::class, 'banUser']);
        Route::post('/users/{id}/unban', [UserController::class, 'unbanUser']);
    });

    /**
     * Route for users
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('users', UserController::class);
    });

    /**
     * Route for user stats
     */
    Route::get('/users/{user_id}/post-interactions', [UserStatsController::class, 'getUserPostsInteractions']);

    /**
     * Route for posts
     */
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    });


    /**
     * Route for comments
     */
    Route::get('/comments', [CommentController::class, 'index']);
    Route::get('/comments/{comment}', [CommentController::class, 'show']);

    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('comments', CommentController::class)->except(['index', 'show']);
        Route::patch('comments/{id}/deleteComment', [CommentController::class, 'deleteComment']);
    });

    /**
     * Routes for user profiles
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('user-profiles', UserProfileController::class);
        Route::post('user-profiles/{id}/enable-temporary-externals', [UserProfileController::class, 'enableTemporaryExternals']);
    });

    /**
     * Route for API keys
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::post('/api-keys', [ApiKeyController::class, 'generate']);
        Route::get('/api-keys', [ApiKeyController::class, 'index']);
        Route::patch('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggleStatus']);
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);
    });

    /**
     * Route for favorites
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::get('/user/favorites', [UserFavoriteController::class, 'index']);
        Route::post('/posts/{post}/favorites', [UserFavoriteController::class, 'store']);
        Route::delete('/posts/{post}/favorites', [UserFavoriteController::class, 'destroy']);
        Route::patch('/posts/{postId}/favorites/important', [UserFavoriteController::class, 'toggleImportant']);

        Route::get('/user/favorites/posts/', [UserFavoriteController::class, 'getFavoritePosts']);
        Route::get('/user/favorites/posts/important', [UserFavoriteController::class, 'getFavoritePosts'])->defaults('important', true);
    });

    /**
     * Route for reports
     */
    Route::post('/reports', [UserReportController::class, 'store']);

    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::get('/reports', [UserReportController::class, 'index']);
        Route::delete('/reports', [UserReportController::class, 'destroy']);
    });

    /**
     * Route for likes
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('likes', UserLikeController::class)->only(['index', 'store']);
        Route::delete('/likes', [UserLikeController::class, 'destroy']);

        Route::get('/user-likes/{userId}/posts', [UserLikeController::class, 'getLikedPosts']);
        Route::get('/user-likes/{userId}/comments', [UserLikeController::class, 'getLikedComments']);
    });

    /**
     * Route for followers
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::get('followers', [UserFollowerController::class, 'getFollowers']);
        Route::get('following', [UserFollowerController::class, 'getFollowing']);
        Route::post('follow/{userId}', [UserFollowerController::class, 'follow']);
        Route::delete('unfollow/{userId}', [UserFollowerController::class, 'unfollow']);
    });

    /**
     * Route for forbidden names
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('forbidden-names', ForbiddenNameController::class);
    });

    /**
     * Route for post allowed values
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('post-allowed-values', PostAllowedValueController::class);
    });

    /**
     * Route for Critical Terms
     */
    Route::middleware(['auth:sanctum', 'privacy-policy-accepted', 'terms-of-service-accepted', 'device-fingerprint', 'email-verified'])->group(function () {
        Route::apiResource('critical-terms', CriticalTermController::class);
    });

    /**
     * Route for CronjobController
     */
    Route::get('/cron/clean-guest-account', [CronjobController::class, 'cleanGuestAccount']);
});
