<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController; // Import the controller to use it in the routes file

use App\Http\Controllers\Api\UserApiController; // Import the controller to use it in the routes file

Route::get('/user', function (Request $request) { 
    return $request->user();
})->middleware('auth:sanctum');




// Public routes that do not require authentication
Route::post('/login', [AuthController::class, 'login']);




// Protected routes that require authentication using Sanctum middleware
Route::middleware('auth:sanctum')->group(function () {
    // User resource route with the controller specified in the second argument
    Route::apiResource('users', UserApiController::class); 
});