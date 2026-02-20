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
        $activationUrl = env('APP_URL', 'http://katchap.com') . '/activate?token=' . $this->token;

        // Use a custom HTML view for a more unique invitation style
        return (new MailMessage)
            ->subject('Welcome to KATCHAP')
            ->view('emails.invite', [
                'name' => $this->name,
                'role' => $this->role,
                'email' => $this->email,
                'password' => $this->password,
                'activationUrl' => $activationUrl,
            ]);
    }
}
