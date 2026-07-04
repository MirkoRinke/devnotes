<?php

namespace App\Services;

use App\Traits\ApiResponses;

class SecurityNotificationService {

    use ApiResponses;


    public function handleEmailConflict(array $errors, string $email): \Illuminate\Http\JsonResponse | null {
        if (config('app.debug', false)) {
            return null;
        }

        $hasOtherErrors = false;

        foreach ($errors as $key => $messages) {
            if ($key === 'email') {
                if ($messages === ['EMAIL_ALREADY_IN_USE']) {
                    continue;
                }
            }
            $hasOtherErrors = true;
            break;
        }

        if (!$hasOtherErrors && isset($errors['email']) && $errors['email'] === ['EMAIL_ALREADY_IN_USE']) {
            // TODO: Send security notification to the email owner to alert them of a registration attempt for their address. This acts as a security notice or a reminder that an account may already exist.
            return $this->successResponse(null, 'User created successfully', 201);
        }
        return null;
    }
}
