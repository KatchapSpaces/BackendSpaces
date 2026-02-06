<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\CreatedByAdminNotification;

class AdminController extends Controller
{
    // Create a manager user directly (admin only)
    public function createManager(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string',
        ]);

        $admin = $request->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'Only admins can create managers'], 403);
        }

        $email = $request->email;
        $name = $request->name ?? null;

        $user = User::where('email', $email)->first();
        $tempPassword = Str::random(10);

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($tempPassword),
                'company_id' => null,
                'email_verified_at' => null,
                'role' => 'manager',
            ]);
        } else {
            $user->role = 'manager';
            $user->password = Hash::make($tempPassword);
            $user->save();
        }

        try {
            $user->notify(new CreatedByAdminNotification($tempPassword));
        } catch (\Exception $e) {
            Log::error('Failed to notify new manager: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'message' => 'Manager created and notified (if mail configured)']);
    }

    // Basic admin dashboard info
    public function dashboard(Request $request)
    {
        try {
            $authUser = $request->user();

            // Default global counts (fallback)
            $userCount = User::count();

            // Use role relationship (roles are stored in a separate table); the 'role' column was dropped in migrations
            $admins = User::whereHas('role', function($q){ $q->where('name','admin'); })->get(['id','email','name','created_at']);
            $managers = User::whereHas('role', function($q){ $q->where('name','manager'); })->get(['id','email','name','created_at']);

            $adminsCount = $admins->count();
            $managersCount = $managers->count();

            // Companies and other role counts (global defaults)
            $companiesCount = \App\Models\Company::count();
            $subcontractors = User::whereHas('role', function($q){ $q->where('name','subcontractor'); })->get(['id','email','name','created_at']);
            $basicUsers = User::whereHas('role', function($q){ $q->where('name','user'); })->get(['id','email','name','created_at']);
            $subcontractorsCount = $subcontractors->count();
            $basicCount = $basicUsers->count();

            // If this is a super admin, scope counts to their organization
            if ($authUser && $authUser->role && $authUser->role->name === 'super_admin') {
                $companyIds = \App\Models\Company::where('created_by_user_id', $authUser->id)->pluck('id');

                // Users in the super admin's companies
                $userCount = User::whereIn('company_id', $companyIds)->count();

                // Accepted admins/managers (assigned to companies created by this super admin)
                $acceptedAdmins = User::whereHas('role', function($q){ $q->where('name','admin'); })->whereIn('company_id', $companyIds)->count();
                $acceptedManagers = User::whereHas('role', function($q){ $q->where('name','manager'); })->whereIn('company_id', $companyIds)->count();

                // Pending invitations for admins/managers created by this super admin
                $pendingAdmins = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','admin')->whereNull('accepted_at')->count();
                $pendingManagers = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','manager')->whereNull('accepted_at')->count();

                $adminsCount = $acceptedAdmins + $pendingAdmins;
                $managersCount = $acceptedManagers + $pendingManagers;

                // Build arrays for listing (accepted users + pending invitations)
                $admins = User::whereHas('role', function($q){ $q->where('name','admin'); })->whereIn('company_id', $companyIds)->get(['id','email','name','created_at']);
                $managers = User::whereHas('role', function($q){ $q->where('name','manager'); })->whereIn('company_id', $companyIds)->get(['id','email','name','created_at']);

                $pendingAdminInvites = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','admin')->whereNull('accepted_at')->get(['id','email','name','created_at']);
                $pendingManagerInvites = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','manager')->whereNull('accepted_at')->get(['id','email','name','created_at']);

                // Also compute companies and other role counts scoped to super admin
                $companiesCount = \App\Models\Company::where('created_by_user_id', $authUser->id)->count();
                $acceptedSubcontractors = User::whereHas('role', function($q){ $q->where('name','subcontractor'); })->whereIn('company_id', $companyIds)->count();
                $acceptedBasic = User::whereHas('role', function($q){ $q->where('name','user'); })->whereIn('company_id', $companyIds)->count();

                // Pending invitations for subcontractor/basic roles created by this super admin
                $pendingSubcontractors = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','subcontractor')->whereNull('accepted_at')->count();
                $pendingBasic = \App\Models\Invitation::where('invited_by', $authUser->id)->where('role','user')->whereNull('accepted_at')->count();

                $subcontractorsCount = $acceptedSubcontractors + $pendingSubcontractors;
                $basicCount = $acceptedBasic + $pendingBasic;

                // Append pending invites to lists (mark as invited)
                foreach ($pendingAdminInvites as $inv) {
                    $admins->push((object)[ 'id' => 'invited_'.$inv->id, 'email' => $inv->email, 'name' => $inv->name, 'created_at' => $inv->created_at, 'invited' => true ]);
                }
                foreach ($pendingManagerInvites as $inv) {
                    $managers->push((object)[ 'id' => 'invited_'.$inv->id, 'email' => $inv->email, 'name' => $inv->name, 'created_at' => $inv->created_at, 'invited' => true ]);
                }

                return response()->json(['status' => true, 'data' => [
                    'user_count' => $userCount,
                    'admins_count' => $adminsCount,
                    'managers_count' => $managersCount,
                    'companies_count' => $companiesCount,
                    'subcontractors_count' => $subcontractorsCount,
                    'basic_count' => $basicCount,
                    'admins' => $admins,
                    'managers' => $managers,
                ]]);
            }

            return response()->json(['status' => true, 'data' => ['user_count' => $userCount, 'admins_count' => $adminsCount, 'managers_count' => $managersCount, 'companies_count' => $companiesCount, 'subcontractors_count' => $subcontractorsCount, 'basic_count' => $basicCount, 'admins' => $admins, 'managers' => $managers]]);
        } catch (\Exception $e) {
            Log::error('AdminController::dashboard error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to fetch admin dashboard data'], 500);
        }
    }

    // Update company name and permissions (admin user updates their settings)
    public function updateSettings(Request $request)
    {
        $request->validate([
            'company' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $admin = $request->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'Only admins can update settings'], 403);
        }

        // If a company_id is provided, use it. Otherwise, if a company name is provided, find or create it and set company_id.
        if ($request->has('company_id')) {
            $admin->company_id = $request->company_id;
        } else if ($request->has('company')) {
            $companyName = $request->company;
            $company = \App\Models\Company::firstOrCreate(
                ['name' => $companyName],
                ['created_by_user_id' => $admin->id]
            );
            $admin->company_id = $company->id;
        }

        if ($request->has('permissions')) {
            $admin->permissions = $request->permissions;
        }

        $admin->save();

        return response()->json(['status' => true, 'message' => 'Settings updated']);
    }

    // Promote or create an admin by email
    public function promoteEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $admin = $request->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'Only admins can promote emails'], 403);
        }

        $email = $request->email;
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->role = 'super_admin';
            // Do not auto-verify a promoted user; preserve their verification state
            // If they're not yet verified, send a verification email
            if (!$user->hasVerifiedEmail()) {
                try {
                    $user->sendEmailVerificationNotification();
                    \Log::info('Sent verification email to promoted user: ' . $user->email);
                } catch (\Exception $e) {
                    \Log::error('Failed to send verification email to promoted user: ' . $e->getMessage());
                }
            }
            $user->save();
            return response()->json(['status' => true, 'message' => 'Existing user promoted to admin']);
        }

        // create a user with password equal to email (insecure, for convenience)
        $new = User::create([
            'name' => null,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($email),
            'company_id' => $admin->company_id,
            'email_verified_at' => null,
            'role' => 'super_admin',
        ]);

        // Send verification email - required before first login
        try {
            $new->sendEmailVerificationNotification();
            \Log::info('Verification email sent to new admin user: ' . $new->email);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email to new admin user: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'message' => 'New admin user created']);
    }
}
