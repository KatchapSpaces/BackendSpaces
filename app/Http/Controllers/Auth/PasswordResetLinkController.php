<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the form to request a password reset link.
     */
    public function create()
    {
        // Return the standard view if it exists; otherwise a minimal fallback.
        if (view()->exists('auth.forgot-password')) {
            return view('auth.forgot-password');
        }

        return response()->view('errors.custom', ['message' => 'Forgot password view not found.'], 200);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request)
    {
        $request->validate(["email" => "required|email"]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
