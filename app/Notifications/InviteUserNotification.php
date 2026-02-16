<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InviteUserNotification extends Notification
{
    use Queueable;

    protected $token;
    protected $email;
    protected $name;
    protected $role;
    protected $password;

    public function __construct($token, $email, $role = null, $name = null, $password = null)
    {
        $this->token = $token;
        $this->email = $email;
        $this->role = $role;
        $this->name = $name;
        $this->password = $password;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Use frontend URL for activation since the user needs to go to the frontend page
        $activationUrl = 'http://katchap.com/activate?token=' . $this->token;

        $mail = (new MailMessage)
            ->subject('Welcome to KATCHAP')
            ->greeting('Hello' . ($this->name ? ' ' . $this->name : '') . '!')
            ->line('You have been invited to join KATCHAP as a ' . ucfirst(str_replace('_', ' ', $this->role ?? 'user')) . '.')
            ->line('Your temporary credentials:')
            ->line('Email: ' . $this->email)
            ->line('Password: ' . ($this->password ?? '(you will set this when activating)'))
            ->line('You can either activate your account (set a new password) or use these credentials to log in:')
            ->action('Activate / Login', $activationUrl)
            ->line('Important: This invitation link will expire in 7 days.')
            ->line('If the button doesn\'t work, you can copy and paste this link into your browser:')
            ->line($activationUrl)
            ->line('If you have any questions, please contact your administrator.')
            ->salutation('Best regards,')
            ->salutation('The KATCHAP Team');

        return $mail;
    }
}
