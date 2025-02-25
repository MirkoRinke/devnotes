<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController; // Import the controller to use it in the routes file

use App\Http\Controllers\Api\UserApiController; // Import the controller to use it in the routes file

use App\Http\Middleware\AccessControl; // Import the middleware to use it in the routes file

// Public routes that do not require authentication route to login and get a token
Route::post('/login', [AuthController::class, 'login']);

// Public routes that do not require authentication route to register a new user
Route::post('/register', [UserApiController::class, 'store']);

// Protected routes that require authentication
Route::middleware(['auth:sanctum', AccessControl::class])->group(function () {
    Route::apiResource('users', UserApiController::class);
});