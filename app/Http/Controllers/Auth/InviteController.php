<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\InviteUserNotification;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string',
            'company' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'role' => 'nullable|string',
            'role_id' => 'nullable|exists:roles,id',
            'password' => 'nullable|string|min:8'
        ]);

        $email = $request->email;
        $name = $request->name;
        $company = $request->company;
        $companyId = $request->company_id;
        $role = $request->role;
        $roleId = $request->role_id;
        $providedPassword = $request->password;

        // If roleId provided, resolve to backend role name
        if ($roleId) {
            $roleModel = Role::find($roleId);
            $role = $roleModel ? $roleModel->name : $role;
        } else {
            // Map frontend roles to backend roles if name provided
            $roleMapping = [
                'Admin' => 'admin',
                'manager' => 'manager',
                'subcontractor' => 'subcontractor',
                'basic' => 'user',
            ];
            $role = $roleMapping[$role] ?? $role;
            $roleModel = Role::where('name', $role)->first();
            $roleId = $roleModel ? $roleModel->id : null;
        }

        $inviter = Auth::user();

        if (!$inviter) {
            return response()->json(['status' => false, 'message' => 'Authentication required'], 401);
        }

        $inviter->load('role');

        // Check if inviter has permission to invite
        if (!$inviter->hasPermission('invite_users')) {
            return response()->json(['status' => false, 'message' => 'Insufficient permissions to invite users'], 403);
        }

        // Define allowed roles for each role (using backend role names)
        $roleHierarchy = [
            'super_admin' => ['admin', 'manager', 'subcontractor', 'user'],
            'admin' => ['manager', 'subcontractor', 'user'],
            'manager' => ['subcontractor', 'user'],
            'subcontractor' => ['user'],
            'user' => [],
        ];

        $inviterRole = $inviter->role ? $inviter->role->name : null;
        if (!$inviterRole || !isset($roleHierarchy[$inviterRole])) {
            return response()->json(['status' => false, 'message' => 'Invalid inviter role'], 403);
        }

        if (!in_array($role, $roleHierarchy[$inviterRole])) {
            return response()->json(['status' => false, 'message' => 'Cannot invite users with this role'], 403);
        }

        // Check if email already invited and not accepted, or if user already exists
        $existingInvitation = Invitation::where('email', $email)->first();
        if ($existingInvitation && !$existingInvitation->accepted_at) {
            return response()->json(['status' => false, 'message' => 'User already invited'], 400);
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            return response()->json(['status' => false, 'message' => 'User already exists'], 400);
        }

        // Delete any existing accepted invitation for this email
        if ($existingInvitation && $existingInvitation->accepted_at) {
            $existingInvitation->delete();
        }

        // Prepare company display name
        $companyName = $company;
        if ($companyId) {
            $companyModel = Company::find($companyId);
            $companyName = $companyModel ? $companyModel->name : $company;
        }

        // Create invitation record
        $token = Str::random(48);
        $expiresAt = now()->addDays(7);

        $invitation = Invitation::create([
            'email' => $email,
            'name' => $name,
            'company' => $companyName,
            'role' => $role,
            'invited_by' => $inviter->id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // Optionally create the user immediately if password was provided (admin prefers creating with password)
        $createdUser = null;
        if ($providedPassword) {
            try {
                // Do not auto-verify invited users — require them to verify their email
                $createdUser = User::create([
                    'name' => $name ?? 'User',
                    'email' => $email,
                    'password' => Hash::make($providedPassword),
                    'role_id' => $roleId,
                    'company_id' => $companyId,
                    'status' => 'active',
                ]);

                // Send Laravel email verification notification so the user must verify before login
                if ($createdUser) {
                    try {
                        $createdUser->sendEmailVerificationNotification();
                        Log::info('Verification email sent to invited user: ' . $createdUser->email);
                    } catch (\Exception $e) {
                        Log::error('Failed to send verification email to created invited user: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                // If user creation fails (race or db), log and continue — invitation still exists
                Log::error('Failed to create user after invite: ' . $e->getMessage());
            }
        }

        // Send invite notification (includes password if set)
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new InviteUserNotification($token, $email, $role, $name, $providedPassword));
        } catch (\Exception $e) {
            Log::error('Failed to send invite: ' . $e->getMessage());
            Log::info('Activation URL: ' . env('APP_URL', 'https://katchap.com') . '/activate?token=' . $token);
        }

        return response()->json([
            'status' => true,
            'message' => 'Invitation sent successfully',
            'user_created' => (bool)$createdUser
        ], 200);
    }

    public function getAvailableRoles(Request $request)
    {

        $user = $request->user();
        if (!$user) {
            \Log::info('InviteController@getAvailableRoles: No authenticated user');
            return response()->json(['roles' => []], 200);
        }

        $user->load('role');
        $roleName = $user->role ? $user->role->name : null;
        $hasInvitePermission = $user->hasPermission('invite_users');
        \Log::info('InviteController@getAvailableRoles', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role_name' => $roleName,
            'has_invite_permission' => $hasInvitePermission
        ]);

        if (!$user->role) {
            \Log::info('InviteController@getAvailableRoles: User has no role');
            return response()->json(['roles' => []], 200);
        }

        // Check if user is allowed to invite
        // Allow if: has invite_users permission OR is super_admin OR is admin
        $isAllowedToInvite = $hasInvitePermission || $roleName === 'super_admin' || $roleName === 'admin';
        
        if (!$isAllowedToInvite) {
            \Log::info('InviteController@getAvailableRoles: User not allowed to invite', [
                'has_permission' => $hasInvitePermission,
                'role_name' => $roleName
            ]);
            return response()->json(['roles' => []], 200);
        }

        $roleHierarchy = [
            'super_admin' => ['admin', 'manager', 'subcontractor', 'user'],
            'admin' => ['manager', 'subcontractor', 'user'],
            'manager' => ['subcontractor', 'user'],
            'subcontractor' => ['user'],
            'user' => [], // regular users cannot invite
        ];

        $availableRoles = $roleHierarchy[$user->role->name] ?? [];

        // Map backend roles to frontend roles
        $frontendMapping = [
            'admin' => 'Admin',
            'manager' => 'manager',
            'subcontractor' => 'subcontractor',
            'user' => 'basic',
        ];

        $mappedRoles = [];
        foreach ($availableRoles as $role) {
            $mappedRoles[] = $frontendMapping[$role] ?? $role;
        }

        return response()->json(['roles' => $mappedRoles], 200);
    }

    /**
     * Cancel / Delete an invitation
     */
    public function destroy(Request $request, Invitation $invitation)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Authentication required'], 401);
        }

        $user->load('role');

        // Allow deletion if the user has invite permission, is super admin, or is the inviter
        if (!($user->hasPermission('invite_users') || ($user->role && $user->role->name === 'super_admin') || $invitation->invited_by === $user->id)) {
            return response()->json(['status' => false, 'message' => 'Insufficient permissions to cancel invitation'], 403);
        }

        if ($invitation->accepted_at) {
            return response()->json(['status' => false, 'message' => 'Invitation already accepted'], 400);
        }

        $invitation->delete();

        return response()->json(['status' => true, 'message' => 'Invitation cancelled successfully'], 200);
    }
}
