<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'name' => 'nullable|string',
            'company' => 'nullable|string',
        ]);

        $start = microtime(true);

        // Check if there's an invitation for this email
        $invitation = \App\Models\Invitation::where('email', $request->email)->first();
        $defaultRoleName = $invitation ? $invitation->role : 'super_admin';

        // Get the role
        $role = \App\Models\Role::where('name', $defaultRoleName)->first();
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Role not found: ' . $defaultRoleName], 500);
        }

        // Create company if provided
        $companyId = null;
        if ($request->company) {
            // Check if company name already exists
            $existingCompany = Company::where('name', $request->company)->first();
            if ($existingCompany) {
                // If company exists AND user is invited to it, assign them to it
                // If company exists AND no invitation exists, reject (prevent joining random companies)
                if (!$invitation) {
                    return response()->json([
                        'status' => false, 
                        'message' => 'A company with this name already exists. Please choose a different name.'
                    ], 422);
                }
                // Company exists and user is invited, so assign them to this company
                $companyId = $existingCompany->id;
            } else {
                // Company doesn't exist, create it
                $company = Company::create([
                    'name' => $request->company,
                    'email' => $request->email,
                    'status' => 'active',
                    'settings' => json_encode([
                        'max_users' => 10,
                        'features' => ['basic']
                    ]),
                    'created_by_user_id' => null // Will be set after user creation
                ]);
                $companyId = $company->id;
            }
        }

        // Create user directly
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role_id' => $role->id,
            // Do NOT auto-verify email at registration — require the user to click the verification link
            'email_verified_at' => null,
            'status' => 'active',
        ];

        // Do NOT assign a company_id to super_admin accounts — they should be global (no single company association)
        if ($role->name !== 'super_admin') {
            $userData['company_id'] = $companyId;
        }

        $user = User::create($userData);

        // Set created_by_user_id for the company
        if ($companyId) {
            Company::find($companyId)->update(['created_by_user_id' => $user->id]);
        }

        // Send verification email
        $mailStart = microtime(true);
        try {
            $user->sendEmailVerificationNotification();
            Log::info('Verification email sent successfully to: ' . $request->email);
        } catch (\Exception $e) {
            Log::error('sendVerificationNotification failed: ' . $e->getMessage());
        }
        $mailDuration = round((microtime(true) - $mailStart) * 1000);

        $totalDuration = round((microtime(true) - $start) * 1000);
        Log::info('register timings', ['mail_ms' => $mailDuration, 'total_ms' => $totalDuration, 'email' => $request->email]);

        return response()->json([
            'status' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 200);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        $user = User::findOrFail($request->id);

        // Log diagnostic info to help debug verification flow
        Log::info('verifyEmail called', [
            'request_id' => $request->id,
            'request_hash' => $request->hash,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_email_verified_at' => $user->email_verified_at,
        ]);

        // Compute and log hash comparison for diagnostics
        $expectedHash = sha1($user->getEmailForVerification());
        $hashMatches = hash_equals((string) $request->hash, $expectedHash);
        Log::info('verifyEmail hash check', [
            'expected_hash' => $expectedHash,
            'provided_hash' => $request->hash,
            'hash_matches' => $hashMatches,
        ]);

        // Validate the verification hash first
        if (!$hashMatches) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid verification link'
            ], 400);
        }

        // If already verified, return success (idempotent)
        $isVerified = $user->hasVerifiedEmail();
        Log::info('verifyEmail verification state before mark', [
            'user_id' => $user->id,
            'email_verified_at' => $user->email_verified_at,
            'hasVerifiedEmail' => $isVerified,
        ]);

        if ($isVerified) {
            return response()->json([
                'status' => true,
                'message' => 'Email already verified',
            ], 200);
        }

        // Mark email as verified
        $user->markEmailAsVerified(); // sets email_verified_at automatically

        return response()->json([
            'status' => true,
            'message' => 'Email verified successfully',
        ], 200);

    }
}
