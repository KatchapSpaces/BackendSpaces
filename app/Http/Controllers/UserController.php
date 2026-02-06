<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get all users across companies (Super Admin only)
     * Super Admin sees all users across all companies (global head)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Only super admin can view users
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Get companies created by this super admin
        $companyIds = \App\Models\Company::where('created_by_user_id', $user->id)->pluck('id');

        // Get users in this super admin's companies
        $users = User::with(['role', 'company'])
            ->whereIn('company_id', $companyIds)
            ->get();

        // Get invitations created by this super admin only
        $invitations = Invitation::where('invited_by', $user->id)
            ->whereNull('accepted_at')
            ->with('inviter')
            ->get();

        // Format invitations to match user structure
        $invitedUsers = $invitations->map(function ($invitation) {
            return [
                'id' => 'invited_' . $invitation->id,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role_id' => null,
                'company_id' => null,
                'status' => 'pending',
                'role' => $invitation->role ? ['name' => $invitation->role] : null,
                'company' => null,
                'invited_at' => $invitation->created_at,
                'invited_by' => $invitation->inviter ? $invitation->inviter->name : 'System',
                'expires_at' => $invitation->expires_at,
                'is_invitation' => true,
            ];
        });

        // Combine active users and invited users
        $allUsers = $users->map(function ($user) {
            return array_merge($user->toArray(), ['is_invitation' => false, 'status' => $user->status ?? 'active']);
        })->concat($invitedUsers);

        return response()->json([
            'status' => true,
            'users' => $allUsers
        ]);
    }

    /**
     * Get a specific user (Super Admin only)
     */
    public function show(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Only super admin can view user details
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'user' => $user->load(['role', 'company'])
        ]);
    }

    /**
     * Update a user (Super Admin only)
     */
    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Only super admin can update users
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent updating super admin user entirely
        if ($user->role && $user->role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin account cannot be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'role_id' => 'sometimes|required|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'status' => 'sometimes|required|in:active,inactive,suspended',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent changing super admin role
        if ($request->has('role_id')) {
            $newRole = Role::find($request->role_id);
            if ($newRole && $newRole->name === 'super_admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Super Admin role cannot be assigned'
                ], 422);
            }
        }

        // Allow super admins to change the company of a user
        $user->update($request->only([
            'name', 'email', 'role_id', 'company_id', 'status', 'country', 'timezone', 'bio'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $user->load(['role', 'company'])
        ]);
    }

    /**
     * Reset user password (Super Admin only) - sends reset email
     */
    public function resetPassword(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Only super admin can reset passwords
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent sending reset email to Super Admin accounts
        if ($user->role && $user->role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Cannot send password reset for Super Admin accounts'
            ], 422);
        }

        // Send reset link to React frontend URL
        $status = Password::sendResetLink(
            ['email' => $user->email],
            function ($user, $token) {
                $resetUrl = "http://localhost:5173/reset-password?token={$token}&email={$user->email}";

                Mail::send([], [], function ($message) use ($user, $resetUrl) {
                    $message->to($user->email)
                        ->subject('Password Reset by Administrator')
                        ->html("Your password has been reset by an administrator. Click here to set a new password: <a href='{$resetUrl}'>Set New Password</a>");
                });
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => true,
                'message' => 'Password reset email sent successfully'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unable to send reset email'
        ], 400);
    }

    /**
     * Block/Deactivate a user (Super Admin only)
     */
    public function toggleStatus(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Only super admin can change user status
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent deactivating super admin
        if ($user->role && $user->role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin cannot be deactivated'
            ], 422);
        }

        $user->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully',
            'user' => $user->load(['role', 'company'])
        ]);
    }

    /**
     * Create a new user (Super Admin only)
     */
    public function store(Request $request)
    {
        $currentUser = $request->user();

        // Only super admin can create users
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent creating super admin users
        $role = Role::find($request->role_id);
        if ($role && $role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin users cannot be created'
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role->name,
            'company_id' => $request->company_id,
            'status' => $request->status ?? 'active',
            'country' => $request->country,
            'timezone' => $request->timezone,
            'bio' => $request->bio,
            // Do not auto-verify admin-created users — require email verification
        ]);

        // Send verification email to newly created user (they must verify before logging in)
        try {
            $user->sendEmailVerificationNotification();
            \Log::info('Verification email sent to admin-created user: ' . $user->email);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email to admin-created user: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => $user->load(['role', 'company'])
        ], 201);
    }

    /**
     * Delete a user (Super Admin only)
     */
    public function destroy(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Only super admin can delete users
        if (!$currentUser->role || $currentUser->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent deleting super admin
        if ($user->role && $user->role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin cannot be deleted'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}
