<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    // ---------------- LOGIN ----------------
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check if this is a super admin trying to login
        $potentialUser = \App\Models\User::where('email', $request->email)->first();
        $isSuperAdmin = false; // Removed super admin system

        // Allow login for existing users, no invitation required for self-registered users
        // But check invitation for role assignment if needed

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        // If user doesn't have role_id, set from invitation or default to super_admin
        if (!$user->role_id) {
            // Try to find an invitation record for this email
            $invitation = \App\Models\Invitation::where('email', $user->email)->first();

            if ($invitation && $invitation->role) {
                $roleModel = \App\Models\Role::where('name', $invitation->role)->first();
            } else {
                // No invitation found — fall back to super_admin as system expects
                $roleModel = \App\Models\Role::where('name', 'super_admin')->first();
            }

            if ($roleModel) {
                $user->role_id = $roleModel->id;
                $user->save();
            } else {
                \Log::warning('LoginController: could not assign role to user on login, role not found', ['email' => $user->email]);
            }
        }

        // Ensure role and its permissions are loaded
        $user->load('role.permissions');
        $permissions = $user->role ? $user->role->permissions->pluck('name')->toArray() : [];

        // Attach a permissions attribute to the user model to simplify frontend usage
        $user->setAttribute('permissions', $permissions);

        // Require email verification before issuing tokens
        if (!$user->hasVerifiedEmail()) {
            // Optionally: log or trigger a resend. For now return a clear message to the client.
            return response()->json([
                'status' => false,
                'message' => 'Please verify your email address before logging in. Check your email for the verification link.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'permissions' => $permissions
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load(['company', 'role']);

        $canEditEmail = $user->role && $user->role->name === 'super_admin';
        // Company association and company edits are restricted: only super_admin may change a user's company
        $canEditCompany = $user->role && $user->role->name === 'super_admin';
        $canEditRole = $user->role && $user->role->name === 'super_admin';

        // Check suspension status
        $isSuspended = $user->status === 'suspended';
        $companySuspended = $user->company && $user->company->status === 'suspended';

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'company' => $user->company,
                'role' => $user->role,
                'country' => $user->country,
                'timezone' => $user->timezone,
                'bio' => $user->bio,
                'avatar' => $user->avatar,
                'image' => isset($user->image) ? $user->image : null,
                'status' => $user->status,
            ],
            'permissions' => [
                'can_edit_email' => $canEditEmail,
                'can_edit_company' => $canEditCompany,
                'can_edit_role' => $canEditRole,
                'read_only_fields' => [], // all fields are conditionally editable
            ],
            'suspension' => [
                'user_suspended' => $isSuspended,
                'company_suspended' => $companySuspended,
                'message' => $isSuspended && $companySuspended
                    ? 'Your account and company have been suspended. Please contact your administrator.'
                    : ($isSuspended
                        ? 'Your account has been suspended. Please contact your administrator.'
                        : ($companySuspended
                            ? 'Your company has been suspended. Please contact your administrator.'
                            : null)),
            ],
        ]);
    }


    // ---------------- GET ALL ROLES ----------------
    public function getAllRoles(Request $request)
    {
        $user = $request->user();

        // Only admin can get all roles
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['roles' => []], 200);
        }

        $roles = \App\Models\Role::all(['id', 'name']);

        return response()->json([
            'status' => true,
            'roles' => $roles
        ]);
    }


    // ---------------- UPDATE PROFILE ----------------
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $canEditEmail = $user->role && $user->role->name === 'super_admin';
        // Company association and company edits are restricted: only super_admin may change a user's company
        $canEditCompany = $user->role && $user->role->name === 'super_admin';
        $canEditRole = $user->role && $user->role->name === 'super_admin';

        // Validation
        $rules = [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:150',
            'image' => 'nullable|image|max:2048',
        ];

        if ($canEditEmail) {
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $user->id;
        }

        if ($canEditCompany) {
            // Accept a company by id (table uses company_id FK now)
            $rules['company_id'] = 'nullable|exists:companies,id';
        }

        if ($canEditRole) {
            $rules['role_id'] = 'required|exists:roles,id';
        }

        $request->validate($rules);

        // Update user fields
        $user->name = $request->name;
        if ($canEditEmail && $request->has('email')) {
            $user->email = $request->email;
        }
        if ($canEditCompany && $request->has('company_id')) {
            $user->company_id = $request->company_id;
        }
        if ($canEditRole && $request->has('role_id')) {
            $user->role_id = $request->role_id;
        }
        $user->country = $request->country ?? $user->country;
        $user->timezone = $request->timezone ?? $user->timezone;
        $user->bio = $request->bio ?? $user->bio;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Save new image in "public/avatars" folder
            $user->avatar = $request->file('image')->store('avatars', 'public');

            // Also set the legacy/alternate `image` column if present so other code expecting `image` works
            if (Schema::hasColumn('users', 'image')) {
                $user->image = $user->avatar;
            }
        }

        $user->save();

        // Reload role relationship
        $user->load('role');

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'role' => $user->role,
                'country' => $user->country,
                'timezone' => $user->timezone,
                'bio' => $user->bio,
                'avatar' => $user->avatar,
                'image' => isset($user->image) ? $user->image : null,
            ]
        ]);
    }
}
