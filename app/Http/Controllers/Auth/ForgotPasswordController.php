<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Send reset link to React frontend URL
        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, $token) {
                $resetUrl = "http://katchap.com/reset-password?token={$token}&email={$user->email}";

                Mail::send([], [], function ($message) use ($user, $resetUrl) {
                    $message->to($user->email)
                        ->subject('Reset Your Password')
                        ->html("Click here to reset your password: <a href='{$resetUrl}'>Reset Password</a>");
                });
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => true,
                'message' => 'Password reset link sent to your email.'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unable to send reset link. Make sure the email exists.'
        ], 400);
    }
}
