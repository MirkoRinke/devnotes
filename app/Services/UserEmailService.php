<?php

namespace App\Services;

use App\Models\User;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PendingEmailVerificationNotification;


class UserEmailService {

    /**
     * Send a pending email verification to the user's new email address.
     *
     * @param User $user The user to send the verification to.
     * @param string $pendingEmail The new email address to verify.
     * @return void
     */
    public function sendPendingEmailVerification(User $user, string $pendingEmail): void {
        $token = Str::random(64);

        $user->forceFill([
            'pending_email' => $pendingEmail,
            'pending_email_token' => hash('sha256', $token),
            'pending_email_expires_at' => now()->addMinutes(60),
        ])->save();

        $url = config('auth.frontend.url') . config('auth.frontend.verify_pending_email_url') . '?' . http_build_query([
            'id' => $user->id,
            'token' => $token,
        ]);

        Notification::route('mail', $pendingEmail)->notify(new PendingEmailVerificationNotification($url));
    }


    /**
     * Confirm the pending email change for a user.
     *
     * @param User $user The user whose pending email is to be confirmed.
     * @param string $token The verification token sent to the pending email.
     * @return bool Returns true if the email was successfully confirmed, false otherwise.
     */
    public function confirmPendingEmail(User $user, string $token): bool {
        if (!$user->pending_email || !$user->pending_email_token || $user->pending_email_expires_at?->isPast()) {
            return false;
        }
        if (!hash_equals($user->pending_email_token, hash('sha256', $token))) {
            return false;
        }
        if (User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists()) {
            return false;
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_expires_at' => null,
        ])->save();

        return true;
    }
}
