<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\API\UserProfileController;

Route::middleware('throttle:api')->group(function () {

    //! Route for users

    // Route to register a new user do not require authentication
    Route::post('/register', [RegisterController::class, 'register']);

    // Route to login and get a token do not require authentication
    Route::post('/login', [AuthController::class, 'login']);

    // Route to logout and revoke token you need to be authenticated
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/tokens', [AuthController::class, 'getUserTokens']);
        Route::delete('/tokens/{id}', [AuthController::class, 'revokeToken']);
        Route::delete('/tokens', [AuthController::class, 'revokeAllTokens']);
    });

    // Route to get all users you need to be authenticated protected by sanctum and Policies
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserApiController::class);
    });


    //! Route for posts

    // Route to get all posts and a single post do not require authentication
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{post}', [PostApiController::class, 'show']);

    // Route to create, update and delete a post you need to be authenticated protected by sanctum and Policies
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostApiController::class)->except(['index', 'show']);
    });

    //! Route for favorites

    // Route to add, remove a favorite and get all favorites you need to be authenticated protected by sanctum and Policies
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/posts/{post}/favorites', [FavoriteController::class, 'addFavorite']);
        Route::delete('/posts/{post}/favorites', [FavoriteController::class, 'removeFavorite']);
        Route::get('/user/favorites', [FavoriteController::class, 'getFavorites']);
    });

    //! Route for user profiles

    // Route to get all user profiles you need to be authenticated protected by sanctum and Policies
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('user_profiles', UserProfileController::class);
    });
});
