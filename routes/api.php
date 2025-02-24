<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UserApiController; // Import the controller to use it in the routes file

Route::get('/user', function (Request $request) { 
    return $request->user();
})->middleware('auth:sanctum');



Route::apiResource('users', UserApiController::class); // Add the resource route to the routes file and pass the controller class as the second argument to the apiResource method.