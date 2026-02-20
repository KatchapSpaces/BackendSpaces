<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\InviteUserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies
     * - Super admin sees all companies (global head)
     * - Admin sees only companies from the super admin who invited them
     * - Others see only companies they created
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Super admin sees only their own companies
        if ($user->hasRole('super_admin')) {
            $companies = Company::where('created_by_user_id', $user->id)
                ->with([
                    'users' => function($query) {
                        $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
                    },
                    'creator' => function($q) {
                        $q->select('id', 'name', 'email'); // Only creator's name and email
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($user->hasRole('admin')) {
            // Admin sees only companies from the super admin who invited them
            $superAdminId = Invitation::where('email', $user->email)
                ->where('role', 'admin')
                ->value('invited_by');
            
            if ($superAdminId) {
                $companies = Company::where('created_by_user_id', $superAdminId)
                    ->with([
                        'users' => function($query) {
                            $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
                        },
                        'creator' => function($q) {
                            $q->select('id', 'name', 'email');
                        }
                    ])
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                // If no invitation found, return empty
                $companies = collect();
            }
        } elseif ($user->hasRole('manager')) {
            // Managers should see companies from their super admin's organization (so they can assign users to companies)
            $managerInv = Invitation::where('email', $user->email)->where('role', 'manager')->first();
            $managerSuperAdminId = null;

            if ($managerInv) {
                $inviter = User::find($managerInv->invited_by);
                if ($inviter) {
                    if ($inviter->hasRole('super_admin')) {
                        $managerSuperAdminId = $managerInv->invited_by;
                    } elseif ($inviter->hasRole('admin')) {
                        $managerSuperAdminId = Invitation::where('email', $inviter->email)
                            ->where('role', 'admin')
                            ->value('invited_by');
                    }
                }
            }

            if ($managerSuperAdminId) {
                $companies = Company::where('created_by_user_id', $managerSuperAdminId)
                    ->with([
                        'users' => function($query) {
                            $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
                        },
                        'creator' => function($q) {
                            $q->select('id', 'name', 'email');
                        }
                    ])
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                $companies = collect();
            }
        } else {
            // Other users see only companies they created
            $companies = Company::where('created_by_user_id', $user->id)
                ->with([
                    'users' => function($query) {
                        $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
                    },
                    'creator' => function($q) {
                        $q->select('id', 'name', 'email');
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Append pending invitations to each company users list (so admins/super-admins can see invited users)
        foreach ($companies as $company) {
            $pendingInvites = \App\Models\Invitation::where('company', $company->name)
                ->where('invited_by', $user->id)
                ->whereNull('accepted_at')
                ->get(['id','email','name','role','created_at']);

            foreach ($pendingInvites as $inv) {
                // Append an object that mirrors a user shape expected by the frontend
                $company->users->push((object)[
                    'id' => 'invited_'. $inv->id,
                    'email' => $inv->email,
                    'name' => $inv->name,
                    'role' => (object)['id' => null, 'name' => $inv->role],
                    'created_at' => $inv->created_at,
                    'invited' => true
                ]);
            }
        }

        return response()->json(['companies' => $companies]);
    }

    /**
     * Store a newly created company
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->role) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only super_admin may create companies via this endpoint
        if (!($user->role && $user->role->name === 'super_admin')) {
            return response()->json(['message' => 'Only super admin can create companies'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            // email is optional on create; format validated, uniqueness checked later to allow same creator reuse
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,inactive,suspended',
        ], [
            'name.unique' => 'A company with this name already exists. Please choose a different name.'
        ]);

        $data = $request->only(['name', 'phone', 'address', 'website', 'status']);
        $data['status'] = $data['status'] ?? 'active';
        // Use invite email as company contact when provided; otherwise use creator (super admin) email
        $inviteEmail = $request->input('invite_email');
        if ($inviteEmail) {
            $data['email'] = $inviteEmail;
        } else {
            $data['email'] = $user->email;
        }
        $data['created_by_user_id'] = $user->id;

        // --- Validate invite email BEFORE creating the company to avoid orphan company rows ---
        if ($inviteEmail) {
            $existingUser = User::where('email', $inviteEmail)->first();
            $existingCompany = Company::where('email', $inviteEmail)->first();

            // If email belongs to an existing user, block and don't create company
            if ($existingUser) {
                return response()->json([
                    'message' => 'The provided email is already registered as a user and cannot be invited.',
                    'invited_email' => $inviteEmail
                ], 422);
            }

            // If email is used as a company contact by another creator, block and don't create company
            if ($existingCompany && (int)$existingCompany->created_by_user_id !== (int)$user->id) {
                return response()->json([
                    'message' => 'The provided email is already used as a company contact and cannot be invited.',
                    'invited_email' => $inviteEmail
                ], 422);
            }
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::create($data);
        
        // Note: When a super_admin creates a company manually, they are the owner but NOT assigned as a user
        // Only when they self-register should they be assigned to their first company
        // Other invited users will be assigned to this company

        // If an invite email and role were provided, create an Invitation and send it
        $inviteEmail = $request->input('invite_email');
        $inviteRole = $request->input('invite_role');
        $inviteName = $request->input('invite_name');

        $invitationSent = false;
        $invitedEmail = null;

        if ($inviteEmail) {
            // Check if email is already registered to a user
            $existingUser = User::where('email', $inviteEmail)->first();
            if ($existingUser) {
                return response()->json([
                    'message' => 'This email is already registered as a user and cannot be invited.',
                    'invited_email' => $inviteEmail
                ], 422);
            }

            // Check if email is already used as a company contact
            $existingCompany = Company::where('email', $inviteEmail)->first();
            if ($existingCompany && (int)$existingCompany->created_by_user_id !== (int)$user->id) {
                return response()->json([
                    'message' => 'This email is already used as a company contact and cannot be invited.',
                    'invited_email' => $inviteEmail
                ], 422);
            }

            // Email is available — create or resend invitation
            $invitedEmail = $inviteEmail;
            $existingInvitation = Invitation::where('email', $inviteEmail)->first();

            try {
                if ($existingInvitation && !$existingInvitation->accepted_at) {
                    // Pending invitation exists: update and resend
                    $token = $existingInvitation->token;
                    $existingInvitation->expires_at = now()->addDays(7);
                    if ($inviteName) $existingInvitation->name = $inviteName;
                    if ($inviteRole) $existingInvitation->role = $inviteRole;
                    $existingInvitation->company = $company->name;
                    $existingInvitation->invited_by = $user->id;
                    $existingInvitation->save();
                } else {
                    // Create new invitation
                    $token = Str::random(48);
                    Invitation::create([
                        'email' => $inviteEmail,
                        'name' => $inviteName ?? null,
                        'company' => $company->name,
                        'role' => $inviteRole ?? null,
                        'invited_by' => $user->id,
                        'token' => $token,
                        'expires_at' => now()->addDays(7),
                    ]);
                }

                // Send invitation email immediately using the HTML view
                try {
                    $activationUrl = env('APP_URL', 'http://katchap.com') . '/activate?token=' . $token;
                    $viewData = [
                        'name' => $inviteName,
                        'role' => $inviteRole,
                        'email' => $inviteEmail,
                        'password' => null,
                        'activationUrl' => $activationUrl,
                    ];

                    \Illuminate\Support\Facades\Mail::send('emails.invite', $viewData, function ($message) use ($inviteEmail) {
                        $message->to($inviteEmail)
                            ->subject('Welcome to KATCHAP');
                    });

                    $invitationSent = true;
                } catch (\Exception $e) {
                    \Log::error('Failed to send invitation: ' . $e->getMessage());
                    \Log::info('Activation URL: ' . env('APP_URL', 'http://katchap.com') . '/activate?token=' . $token);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create/resend invitation: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Company created successfully',
            'company' => $company->loadCount('users')->load('creator:id,name,email'),
            'invitation_sent' => $invitationSent,
            'invited_email' => $invitedEmail,
        ], 201);
    }

    /**
     * Display the specified company (Super Admin only)
     */
    public function show(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can view company details
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load users including created_at so frontend can render "Joined" correctly
        $company = $company->load([
            'users' => function($query) {
                $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
            },
            'creator' => function($q) {
                $q->select('id', 'name', 'email');
            }
        ]);

        // Append pending invitations to company users (same as index) so invites are visible in the expanded company view
        $pendingInvites = \App\Models\Invitation::where('company', $company->name)
            ->where('invited_by', $user->id)
            ->whereNull('accepted_at')
            ->get(['id','email','name','role','created_at']);

        foreach ($pendingInvites as $inv) {
            $company->users->push((object)[
                'id' => 'invited_'. $inv->id,
                'email' => $inv->email,
                'name' => $inv->name,
                'role' => (object)['id' => null, 'name' => $inv->role],
                'created_at' => $inv->created_at,
                'invited' => true
            ]);
        }

        return response()->json(['company' => $company]);
    }

    /**
     * Update the specified company (Super Admin only)
     */
    public function update(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can update companies
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            // Allow email but validate format; duplicate check handled below so super_admin emails may be reused
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,inactive,suspended',
            'settings' => 'nullable|array',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'address', 'website', 'status', 'settings']);

        // Enforce company contact email uniqueness when updating
        if (isset($data['email']) && $data['email']) {
            $email = $data['email'];
            $existingCompany = Company::where('email', $email)->where('id', '!=', $company->id)->first();
            if ($existingCompany) {
                return response()->json(['message' => 'The email has already been taken. Please use a different contact email.'], 422);
            }
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        // Handle status changes
        if (isset($data['status'])) {
            if ($data['status'] === 'active' && $company->status !== 'active') {
                $company->activate();
            } elseif ($data['status'] === 'suspended' && $company->status !== 'suspended') {
                $company->suspend();
            } elseif ($data['status'] === 'inactive' && $company->status !== 'inactive') {
                $company->deactivate();
            }
        }

        // Company name change does not require updating users table — users reference companies by company_id
        if (isset($data['name']) && $data['name'] !== $company->name) {
            // no-op: keep users' company_id pointing to this company
        }

        $company->update($data);

        return response()->json([
            'message' => 'Company updated successfully',
            'company' => $company->loadCount('users')
        ]);
    }

    /**
     * Remove the specified company and all its users (Super Admin only)
     */
    public function destroy(Request $request, Company $company)
    {
        $user = $request->user();

        // Only super admin can delete companies
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only the super-admin who CREATED this company can delete it
        if ((int)$user->id !== (int)$company->created_by_user_id) {
            return response()->json(['message' => 'Only the creating super-admin can delete this company'], 403);
        }

        try {
            DB::transaction(function () use ($company) {
                // Delete all users associated with this company, but never delete Super Admin accounts
                $company->users()->whereHas('role', function($q) {
                    $q->where('name', '!=', 'super_admin');
                })->delete();

                // Delete company logo if exists
                if ($company->logo) {
                    Storage::disk('public')->delete($company->logo);
                }

                // Delete the company
                $company->delete();
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete company',
                'error' => $e->getMessage(),
                'redirect' => false
            ], 500);
        }

        // Add a redirect flag for frontend to know to go back to company list
        return response()->json([
            'message' => 'Company and all associated users deleted successfully',
            'redirect' => true
        ]);
    }

    /**
     * Activate a company (Super Admin only)
     */
    public function activate(Request $request, Company $company)
    {
        $user = $request->user();

        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company->activate();

        return response()->json([
            'message' => 'Company activated successfully',
            'company' => $company
        ]);
    }

    /**
     * Suspend a company (Super Admin only)
     */
    public function suspend(Request $request, Company $company)
    {
        $user = $request->user();

        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company->suspend();

        return response()->json([
            'message' => 'Company suspended successfully',
            'company' => $company
        ]);
    }

    /**
     * Get global system analytics (Super Admin only)
     */
    public function analytics(Request $request)
    {
        $user = $request->user();

        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $companiesByStatus = Company::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'suspended_companies' => Company::where('status', 'suspended')->count(),
            'total_users' => User::count(),
            'companies_by_status' => [
                'active' => $companiesByStatus['active'] ?? 0,
                'suspended' => $companiesByStatus['suspended'] ?? 0,
                'inactive' => $companiesByStatus['inactive'] ?? 0,
            ],
            'recent_companies' => Company::latest()->take(5)->get(['id', 'name', 'status', 'created_at']),
        ]);
    }

    /**
     * Get all users in the system (Super Admin, Admin, and Manager only)
     * Filters available users based on role hierarchy:
     * - Super Admin: sees all users
     * - Admin: sees manager, subcontractor, basic, granular, design_team (no super_admin or admin)
     * - Manager: sees subcontractor, basic, granular, design_team (no super_admin, admin, or manager)
     */
    public function getAllUsers(Request $request)
    {
        $user = $request->user();

        if (!$user->role || !in_array($user->role->name, ['super_admin', 'admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized. Only super_admin, admin, and manager can access user list'], 403);
        }

        $companyIds = [];
        $excludeRoles = [];

        if ($user->hasRole('super_admin')) {
            // Super admin can see all companies they created
            $companyIds = Company::where('created_by_user_id', $user->id)->pluck('id');
            $excludeRoles = []; // See all roles
        } elseif ($user->hasRole('admin')) {
            // Admin can see companies from the super admin who invited them
            $superAdminId = Invitation::where('email', $user->email)
                ->where('role', 'admin')
                ->value('invited_by');
            
            if ($superAdminId) {
                $companyIds = Company::where('created_by_user_id', $superAdminId)->pluck('id');
            }
            // Admin cannot see super_admin or other admin users
            $excludeRoles = ['super_admin', 'admin'];
        } elseif ($user->hasRole('manager')) {
            // Manager can see companies from their organization
            $managerInv = Invitation::where('email', $user->email)->where('role', 'manager')->first();
            $superAdminId = null;

            if ($managerInv) {
                $inviter = User::find($managerInv->invited_by);
                if ($inviter) {
                    if ($inviter->hasRole('super_admin')) {
                        $superAdminId = $managerInv->invited_by;
                    } elseif ($inviter->hasRole('admin')) {
                        $superAdminId = Invitation::where('email', $inviter->email)
                            ->where('role', 'admin')
                            ->value('invited_by');
                    }
                }
            }

            if ($superAdminId) {
                $companyIds = Company::where('created_by_user_id', $superAdminId)->pluck('id');
            }
            // Manager cannot see super_admin, admin, or other manager users
            $excludeRoles = ['super_admin', 'admin', 'manager'];
        }

        // Get users in the allowed companies, excluding restricted roles
        $users = User::with(['role', 'company'])
            ->whereIn('company_id', $companyIds)
            ->whereHas('role', function($q) use ($excludeRoles) {
                if (!empty($excludeRoles)) {
                    $q->whereNotIn('name', $excludeRoles);
                }
            })
            ->select('id', 'name', 'email', 'role_id', 'company_id', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get invitations created by this user only (or their organization if viewing as admin/manager)
        $invitations = Invitation::where('invited_by', $user->id)
            ->whereNull('accepted_at')
            ->with('inviter')
            ->get();

        // Also filter invitations by role if user is admin or manager
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            $invitations = $invitations->filter(function($inv) use ($excludeRoles) {
                return !in_array($inv->role, $excludeRoles);
            });
        }

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
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name] : null,
                'company' => $user->company ? ['id' => $user->company->id, 'name' => $user->company->name, 'status' => $user->company->status] : null,
            ];
        })->concat($invitedUsers);

        return response()->json($allUsers);
    }
}