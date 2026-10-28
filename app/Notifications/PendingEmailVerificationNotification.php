<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingEmailVerificationNotification extends Notification {

    /**
     * Create a new notification instance.
     */
    public function __construct(private string $url) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject('Confirm your new email address')
            ->line('You requested to change your email address.')
            ->action('Confirm email', $this->url)
            ->line('This link is valid for 60 minutes. If you did not request this, you can ignore this email.');
    }
}
