<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController; // Import the controller to use it in the routes file

use App\Http\Controllers\Api\UserApiController; // Import the controller to use it in the routes file

use App\Http\Middleware\CheckRole; // Import the middleware to use it in the routes file

Route::get('/user', function (Request $request) { 
    return $request->user();
})->middleware('auth:sanctum');




// Public routes that do not require authentication route to login and get a token
Route::post('/login', [AuthController::class, 'login']);

// Public routes that do not require authentication route to register a new user
Route::post('/register', [UserApiController::class, 'store']);

// Protected routes that require authentication using Sanctum middleware
Route::middleware('auth:sanctum')->group(function () {
    // User resource route with the controller specified in the second argument
    Route::apiResource('users', UserApiController::class)->except(['store', 'destroy']);
});


// Protected routes that require authentication and the role of the user is admin
Route::middleware(['auth:sanctum', CheckRole::class . ':admin'])->group(function () {
    Route::delete('/users/{user}', [UserApiController::class, 'destroy']);
});
