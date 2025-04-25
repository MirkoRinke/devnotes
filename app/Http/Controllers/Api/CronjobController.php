<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

use App\Traits\ApiResponses; // example $this->successResponse($users, 'Users retrieved successfully', 200);

use App\Services\GuestAccountService;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CronjobController extends Controller {

    use ApiResponses;

    protected $userApiController;
    protected $guestAccountService;

    public function __construct(UserApiController $userApiController, GuestAccountService $guestAccountService) {
        $this->userApiController = $userApiController;
        $this->guestAccountService = $guestAccountService;
    }


    public function cleanGuestAccount(): JsonResponse {
        try {
            $guestAccount = User::where('account_purpose', 'guest')->first();

            if (!$guestAccount) {
                // If no guest account exists, create a new one
                $this->guestAccountService->createGuestAccount();
                return $this->successResponse([], 'Guest account created successfully', 201);
            }

            $result = $this->userApiController->handleGuestAccountDeletion($guestAccount);

            return $result;
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
