<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return response()->json($users);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use Carbon\Carbon;

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($post, 'Post created successfully', 201);


class AuthController extends Controller {

    use ApiResponses; // Use the ApiResponses trait in the controller
 

    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 'CREDENTIALS_INCORRECT', 401);
        }
    
        $token = $user->createToken($request->device_name);
        $token->accessToken->expires_at = Carbon::now()->addDays(7);
        $token->accessToken->save();
    
        return $this->successResponse(['accessToken' => $token->plainTextToken,'type' => 'Bearer' ],'Login successful', 200);
    }


    /**
     * Logout a user and revoke the token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout successful', 200);
    }
}




