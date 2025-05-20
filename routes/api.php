<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentApiController;
use App\Http\Controllers\Api\CriticalTermController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ForbiddenNameController;
use App\Http\Controllers\Api\PostAllowedValueController;
use App\Http\Controllers\Api\UserFollowerController;
use App\Http\Controllers\Api\UserLikeController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\Api\UserReportController;
use App\Http\Controllers\Api\CronjobController;

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
 * 4. email-verified: Ensures the user's email is verified
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

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/email/verification-notification', [RegisterController::class, 'resendVerificationEmail']);
    });

    /**
     * Route for login
     */
    Route::post('/login', [AuthController::class, 'login']);

    /**
     * Route for logout and tokens
     */
    Route::middleware(['auth:sanctum'])->group(function () {
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
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::post('/users/{id}/ban', [UserApiController::class, 'banUser']);
        Route::post('/users/{id}/unban', [UserApiController::class, 'unbanUser']);
    });

    /**
     * Route for users
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('users', UserApiController::class);
    });

    /**
     * Route for posts
     */
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{post}', [PostApiController::class, 'show']);
    Route::get('/posts/{user_id}/received-interactions', [PostApiController::class, 'getUserPostsInteractions']);

    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('posts', PostApiController::class)->except(['index', 'show']);
    });

    /**
     * Route for comments
     */
    Route::get('/comments', [CommentApiController::class, 'index']);
    Route::get('/comments/{comment}', [CommentApiController::class, 'show']);

    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('comments', CommentApiController::class)->except(['index', 'show']);
        Route::patch('comments/{id}/deleteComment', [CommentApiController::class, 'deleteComment']);
    });

    /**
     * Routes for user profiles
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('user-profiles', UserProfileController::class);
        Route::post('user-profiles/{id}/enable-temporary-externals', [UserProfileController::class, 'enableTemporaryExternals']);
    });

    /**
     * Route for API keys
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::post('/api-keys', [ApiKeyController::class, 'generate']);
        Route::get('/api-keys', [ApiKeyController::class, 'index']);
        Route::patch('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggleStatus']);
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);
    });

    /**
     * Route for favorites
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::get('/user/favorites', [FavoriteController::class, 'index']);
        Route::post('/posts/{post}/favorites', [FavoriteController::class, 'store']);
        Route::delete('/posts/{post}/favorites', [FavoriteController::class, 'destroy']);

        Route::get('/user/favorites/posts', [FavoriteController::class, 'getFavoritePosts']);
    });

    /**
     * Route for reports
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('reports', UserReportController::class)->only(['index', 'store']);
        Route::delete('/reports', [UserReportController::class, 'destroy']);
    });

    /**
     * Route for likes
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('likes', UserLikeController::class)->only(['index', 'store']);
        Route::delete('/likes', [UserLikeController::class, 'destroy']);

        Route::get('/user-likes/posts', [UserLikeController::class, 'getLikedPosts']);
        Route::get('/user-likes/comments', [UserLikeController::class, 'getLikedComments']);
    });

    /**
     * Route for followers
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::get('followers', [UserFollowerController::class, 'getFollowers']);
        Route::get('following', [UserFollowerController::class, 'getFollowing']);
        Route::post('follow/{userId}', [UserFollowerController::class, 'follow']);
        Route::delete('unfollow/{userId}', [UserFollowerController::class, 'unfollow']);
    });

    /**
     * Route for forbidden names
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('forbidden-names', ForbiddenNameController::class);
    });

    /**
     * Route for post allowed values
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('post-allowed-values', PostAllowedValueController::class);
    });

    /**
     * Route for Critical Terms
     */
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('critical-terms', CriticalTermController::class);
    });

    /**
     * Route for CronjobController
     */
    Route::get('/cron/clean-guest-account', [CronjobController::class, 'cleanGuestAccount']);
});
