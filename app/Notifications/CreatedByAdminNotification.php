<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CreatedByAdminNotification extends Notification
{
    use Queueable;

    protected $tempPassword;

    public function __construct($tempPassword)
    {
        $this->tempPassword = $tempPassword;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontend = env('APP_URL', 'https://katchap.com');
        return (new MailMessage)
            ->subject('Account Created')
            ->line('An admin created an account for you.')
            ->line('Temporary password: ' . $this->tempPassword)
            ->action('Login', $frontend)
            ->line('Please login and change your password.');
    }
}
