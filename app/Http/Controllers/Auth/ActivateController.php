<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ActivateController extends Controller
{
    public function activate(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|min:6|confirmed',
        ]);

        $invite = Invitation::where('token', $request->token)->first();
        if (!$invite) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired activation token'], 400);
        }

        if ($invite->expires_at && now()->greaterThan($invite->expires_at)) {
            $invite->delete();
            return response()->json(['status' => false, 'message' => 'Activation token has expired'], 400);
        }

        $role = \App\Models\Role::where('name', $invite->role)->first();
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Invalid role'], 400);
        }

        // Handle company assignment - find existing company or create new one
        $companyId = null;
        if ($invite->company) {
            $company = \App\Models\Company::where('name', $invite->company)->first();
            if (!$company) {
                // Create new company if it doesn't exist
                $company = \App\Models\Company::create([
                    'name' => $invite->company,
                    'email' => $invite->email, // Use invite email as company contact
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
            }
            $companyId = $company->id;
        }

        $user = User::where('email', $invite->email)->first();
        if (!$user) {
            $userData = [
                'name' => $invite->name ?? null,
                'email' => $invite->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
                'email_verified_at' => now(),
                'status' => 'active',
            ];

            // Do not assign company_id for super_admin role
            if ($role->name !== 'super_admin') {
                $userData['company_id'] = $companyId;
            }

            $user = User::create($userData);
        } else {
            $user->password = Hash::make($request->password);
            $user->role_id = $role->id;
            if ($role->name !== 'super_admin') {
                $user->company_id = $companyId ?? $user->company_id;
            } else {
                // Ensure super_admin company_id is null
                $user->company_id = null;
            }
            $user->user_type = $invite->frontend_role === 'granular' ? 'granular' : 'basic';
            $user->email_verified_at = now();
            $user->status = 'active';
            $user->save();
        }

        $invite->accepted_at = now();
        $invite->save();

        return response()->json(['status' => true, 'message' => 'Account activated successfully'], 200);
    }
}
