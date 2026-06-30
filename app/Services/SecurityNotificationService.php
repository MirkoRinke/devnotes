<?php

namespace App\Services;

class SecurityNotificationService {


    public function handleEmailConflict(array $errors, string $email): \Illuminate\Http\JsonResponse | null {
        if (config('app.debug', false)) {
            return null;
        }

        if (isset($errors['email']) && $errors['email'] === ['EMAIL_ALREADY_IN_USE']) {
            // TODO: Send security notification to the email owner to alert them of a registration attempt for their address. This acts as a security notice or a reminder that an account may already exist.
            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'code' => 201,
                'count' => 1,
                'data' => [
                    'data' => null
                ]
            ], 201);
        }
        return null;
    }
}
