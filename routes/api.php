<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController; // Import the controller to use it in the routes file
use App\Http\Controllers\Api\RegisterController; // Import the controller to use it in the routes file

use App\Http\Controllers\Api\UserApiController; // Import the controller to use it in the routes file
use App\Http\Controllers\Api\PostApiController; // Import the controller to use it in the routes file

use App\Http\Controllers\Api\FavoriteController; // Import the controller to use it in the routes file

use App\Http\Controllers\API\UserProfileController; // Import the controller to use it in the routes file

Route::middleware('throttle:api')->group(function () {

    //! Route to register a new user

    // Public routes that do not require authentication route to register a new user
    Route::post('/register', [RegisterController::class, 'register']);

    //! Route to login and get a token

    // Public routes that do not require authentication route to login and get a token
    Route::post('/login', [AuthController::class, 'login']);

    //! Route to logout
    // Protected routes that require authentication
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });


    //! Route to all users
    // Protected routes that require authentication and access control
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserApiController::class);
    });

    //! Route to posts 
    // Public routes that do not require authentication
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{post}', [PostApiController::class, 'show']);

    // Protected routes that require authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostApiController::class)->except(['index', 'show']);
    });

    //! Route to favorites
    // Protected routes that require authentication and access control
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/posts/{post}/favorites', [FavoriteController::class, 'addFavorite']);
        Route::delete('/posts/{post}/favorites', [FavoriteController::class, 'removeFavorite']);
        Route::get('/user/favorites', [FavoriteController::class, 'getFavorites']);
    });

    //! Route to user profiles
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('user_profiles', UserProfileController::class);
    });
});
