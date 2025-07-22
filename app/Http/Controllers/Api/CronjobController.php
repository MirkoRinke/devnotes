<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;

use App\Models\User;

use App\Traits\ApiResponses;

use App\Services\GuestAccountService;

use Exception;

/**
 * Controller handling scheduled maintenance tasks via API endpoints.
 * 
 * This controller provides endpoints for system maintenance operations that
 * are typically called by scheduled tasks or cron jobs rather than direct user interaction.
 * All endpoints require API key authentication for security.
 */
class CronjobController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     *  The Service used in the controller
     */
    protected $guestAccountService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(GuestAccountService $guestAccountService) {
        $this->guestAccountService = $guestAccountService;
    }

    /**
     * Clean Guest Account
     * 
     * Endpoint: POST /api/cron/clean-guest-account
     *
     * Resets the guest account by completely deleting and recreating it with the same base information.
     * This process removes all guest-related data (posts, comments, settings and any changes made to the account).
     * The purpose is to provide a "fresh" guest account while maintaining the same account identifier.
     * If no guest account exists, a new one will be created.
     * 
     * This endpoint is typically called by a scheduled task rather than directly by users.
     * 
     * @group System Maintenance
     *
     * @header X-API-Key string required A valid API key for authentication. Example: LDWOTtb7GEnZo0b5KDgzat9Kl51ROY6WkviWCJiP
     * @queryParam api_key string An alternative way to provide the API key if header is not possible. Example: LDWOTtb7GEnZo0b5KDgzat9Kl51ROY6WkviWCJiP
     *
     * Example URL: /api/cron/clean-guest-account   || X-API-Key: LDWOTtb7GEnZo0b5KDgzat9Kl51ROY6WkviWCJiP
     * 
     * Example URL: /api/cron/clean-guest-account/?api_key=LDWOTtb7GEnZo0b5KDgzat9Kl51ROY6WkviWCJiP
     *  
     * @response status=200 scenario="Guest account reset" {
     *   "status": "success",
     *   "message": "Guest account reset successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=201 scenario="Guest account created" {
     *   "status": "success",
     *   "message": "Guest account created successfully",
     *   "code": 201,
     *   "count": 0,
     *   "data": []
     * }
     * 
     * @response status=500 scenario="Guest reset failed" {
     *   "status": "error",
     *   "message": "Failed to reset guest account",
     *   "code": 500,
     *   "errors": "GUEST_RESET_FAILED"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @response status=401 scenario="Missing API key" {
     *   "status": "error",
     *   "message": "This request requires a valid API key in the X-API-Key header or api_key query parameter",
     *   "code": 401,
     *   "errors": "API_KEY_MISSING"
     * }
     *
     * @response status=401 scenario="Invalid API key" {
     *   "status": "error",
     *   "message": "The provided API key is invalid or has been deactivated",
     *   "code": 401,
     *   "errors": "INVALID_API_KEY"
     * }
     */
    public function cleanGuestAccount(): JsonResponse {
        try {
            $guestAccount = User::where('account_purpose', 'guest')->first();

            if (!$guestAccount) {
                $this->guestAccountService->createGuestAccount();
                return $this->successResponse([], 'Guest account created successfully', 201);
            }

            $success = $this->guestAccountService->resetGuestAccount($guestAccount);
            if (!$success) {
                return $this->errorResponse('Failed to reset guest account', 'GUEST_RESET_FAILED', 500);
            }

            return $this->successResponse(null, 'Guest account reset successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
