<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Get all roles (Super Admin only)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Only super admin can manage roles
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Get all roles with their permissions (fresh from DB, no cache)
        $rolesData = Role::query()
            ->with('permissions')
            ->get();
        
        $roles = $rolesData->map(function($role) use ($user) {
            // By default count actual users assigned to this role
            $userCount = $role->users()->count();

            // If current user is super_admin, scope the count to their organization
            if ($user && $user->role && $user->role->name === 'super_admin') {
                // Companies that belong to this super admin
                $companyIds = \App\Models\Company::where('created_by_user_id', $user->id)->pluck('id')->toArray();

                // Fallback: some older records might not have created_by_user_id set; try matching by company email
                if (empty($companyIds)) {
                    $companyIds = \App\Models\Company::where('email', $user->email)->pluck('id')->toArray();
                    \Log::warning('Company ownership lookup fallback used for super admin', ['user_id' => $user->id, 'email' => $user->email, 'company_ids' => $companyIds]);
                } else {
                    \Log::info('CompanyIds resolved for super admin', ['user_id' => $user->id, 'company_ids' => $companyIds]);
                }

                // Ensure we only count users inside this super admin's companies (if none exist, result should be zero)
                if (empty($companyIds)) {
                    $acceptedCount = 0;
                } else {
                    $acceptedCount = \App\Models\User::where('role_id', $role->id)
                        ->whereIn('company_id', $companyIds)
                        ->count();
                }

                // Pending invitations for this role:
                // - match either the legacy `role` field or the newer `frontend_role` field (case-insensitive)
                // - count invites that are either created by this super admin OR target a company owned by this super admin
                $companyNames = \App\Models\Company::whereIn('id', $companyIds)->pluck('name')->toArray();

                $pendingInvitesQuery = \App\Models\Invitation::whereNull('accepted_at')
                    ->where(function($q) use ($role) {
                        $q->whereRaw('LOWER(role) = ?', [strtolower($role->name)])
                          ->orWhereRaw('LOWER(frontend_role) = ?', [strtolower($role->name)]);
                    })
                    ->where(function($q) use ($user, $companyNames) {
                        $q->where('invited_by', $user->id);
                        if (!empty($companyNames)) {
                            $q->orWhereIn('company', $companyNames);
                        }
                    });

                $pendingInvites = $pendingInvitesQuery->count();
                \Log::info('Pending invites query', ['role' => $role->name, 'company_names' => $companyNames, 'pending' => $pendingInvites]);

                $userCount = $acceptedCount + $pendingInvites;
            }

            return [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
                'user_count' => $userCount,
                'permissions' => $role->permissions->map(function($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'pivot' => [
                            'scope' => $permission->pivot->scope ?? 'full'
                        ]
                    ];
                })->toArray(),
            ];
        });

        // Debug logging - check that permissions are being loaded
        \Log::info('Roles fetched for super admin', [
            'roles_count' => $roles->count(),
            'roles_have_permissions' => $roles->every(function($role) {
                return count($role['permissions']) > 0;
            })
        ]);

        return response()->json([
            'status' => true,
            'roles' => $roles->toArray()
        ]);
    }



    /**
     * Show role details (Super Admin only)
     */
    public function show(Request $request, Role $role)
    {
        $user = $request->user();

        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Determine companies owned by this super admin
        $companyIds = \App\Models\Company::where('created_by_user_id', $user->id)->pluck('id')->toArray();
        if (empty($companyIds)) {
            $companyIds = \App\Models\Company::where('email', $user->email)->pluck('id')->toArray();
        }

        // Accepted users in this super admin's companies with this role
        if (empty($companyIds)) {
            $acceptedUsers = collect([]);
        } else {
            $acceptedUsers = \App\Models\User::where('role_id', $role->id)
                ->whereIn('company_id', $companyIds)
                ->select('id','email','company_id')
                ->get();
        }

        // Pending invitations for this role scoped to this super admin
        $companyNames = \App\Models\Company::whereIn('id', $companyIds)->pluck('name')->toArray();

        $pendingInvites = \App\Models\Invitation::whereNull('accepted_at')
            ->where(function($q) use ($role) {
                $q->whereRaw('LOWER(role) = ?', [strtolower($role->name)])
                  ->orWhereRaw('LOWER(frontend_role) = ?', [strtolower($role->name)]);
            })
            ->where(function($q) use ($user, $companyNames) {
                $q->where('invited_by', $user->id);
                if (!empty($companyNames)) {
                    $q->orWhereIn('company', $companyNames);
                }
            })
            ->select('id','email','company')
            ->get();

        $formatted = [
            'id' => $role->id,
            'name' => $role->name,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'user_count' => $acceptedUsers->count() + $pendingInvites->count(),
            'accepted_users' => $acceptedUsers,
            'pending_invites' => $pendingInvites,
            'permissions' => $role->permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'pivot' => [
                        'scope' => $permission->pivot->scope ?? 'full'
                    ]
                ];
            })->toArray(),
        ];

        return response()->json([
            'status' => true,
            'role' => $formatted
        ]);
    }

    /**
     * Create a new role (Super Admin only)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Only super admin can create roles
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:rbac_permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent creating admin role
        if (strtolower($request->name) === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin role cannot be created'
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name
        ]);

        // Attach permissions if provided
        if ($request->has('permissions')) {
            $role->permissions()->attach($request->permissions);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }

    /**
     * Update a role (Super Admin only)
     */
    public function update(Request $request, Role $role)
    {
        $user = $request->user();

        // Only super admin can update roles
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent updating admin role
        if ($role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin role cannot be modified'
            ], 422);
        }

        \Log::info('=== UPDATE REQUEST RECEIVED ===', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'all_request_data' => $request->all(),
            'has_permissions_key' => $request->has('permissions'),
            'permissions_array' => $request->permissions,
            'permissions_count' => is_array($request->permissions) ? count($request->permissions) : 'NOT_AN_ARRAY'
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:rbac_permissions,id'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update([
            'name' => $request->name
        ]);

        // Sync permissions
        if ($request->has('permissions')) {
            \Log::info('SYNCING PERMISSIONS', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_to_sync' => $request->permissions,
                'count' => count($request->permissions)
            ]);
            $role->permissions()->sync($request->permissions);
        } else {
            \Log::warning('NO PERMISSIONS IN REQUEST', [
                'role_id' => $role->id,
                'role_name' => $role->name
            ]);
        }

        // Reload the role with updated permissions
        $updatedRole = $role->load('permissions');
        \Log::info('Role updated successfully', [
            'role_id' => $role->id,
            'permissions_count' => $updatedRole->permissions->count(),
            'permission_names' => $updatedRole->permissions->pluck('name')->toArray(),
            'permission_ids' => $updatedRole->permissions->pluck('id')->toArray()
        ]);

        // Return role in the same format as index() for consistency
        $formattedRole = [
            'id' => $updatedRole->id,
            'name' => $updatedRole->name,
            'created_at' => $updatedRole->created_at,
            'updated_at' => $updatedRole->updated_at,
            'user_count' => $updatedRole->users()->count(),
            'permissions' => $updatedRole->permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'pivot' => [
                        'scope' => $permission->pivot->scope ?? 'full'
                    ]
                ];
            })->toArray(),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully',
            'role' => $formattedRole
        ]);
    }

    /**
     * Delete a role (Super Admin only)
     */
    public function destroy(Request $request, Role $role)
    {
        $user = $request->user();

        // Only super admin can delete roles
        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent deleting admin role
        if ($role->name === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Admin role cannot be deleted'
            ], 422);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete role that has assigned users'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Diagnostic endpoint to help debug role counts (Super Admin only)
     * NOTE: Temporary - used for investigation only
     */
    public function diagnose(Request $request)
    {
        $user = $request->user();

        if (!$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Determine companies owned by this super admin
        $companyIds = \App\Models\Company::where('created_by_user_id', $user->id)->pluck('id')->toArray();
        // Fallback lookup by email if none found
        if (empty($companyIds)) {
            $companyIds = \App\Models\Company::where('email', $user->email)->pluck('id')->toArray();
        }

        $companies = \App\Models\Company::whereIn('id', $companyIds)->get(['id','name','email','created_by_user_id']);

        $roles = Role::with('permissions')->get();
        $details = [];
        foreach ($roles as $role) {
            if (empty($companyIds)) {
                $acceptedUsers = collect([]);
            } else {
                $acceptedUsers = \App\Models\User::where('role_id', $role->id)
                    ->whereIn('company_id', $companyIds)
                    ->select('id','email','company_id')
                    ->get();
            }

            $companyNames = \App\Models\Company::whereIn('id', $companyIds)->pluck('name')->toArray();

            $pendingInvites = \App\Models\Invitation::whereNull('accepted_at')
                ->where(function($q) use ($role) {
                    $q->whereRaw('LOWER(role) = ?', [strtolower($role->name)])
                      ->orWhereRaw('LOWER(frontend_role) = ?', [strtolower($role->name)]);
                })
                ->where(function($q) use ($user, $companyNames) {
                    $q->where('invited_by', $user->id);
                    if (!empty($companyNames)) {
                        $q->orWhereIn('company', $companyNames);
                    }
                })
                ->select('id','email','company')
                ->get();

            $details[] = [
                'role' => $role->name,
                'accepted_count' => $acceptedUsers->count(),
                'accepted_users' => $acceptedUsers,
                'pending_count' => $pendingInvites->count(),
                'pending_invites' => $pendingInvites,
            ];
        }

        return response()->json([
            'status' => true,
            'company_ids' => $companyIds,
            'companies' => $companies,
            'details' => $details
        ]);
    }

    /**
     * Get all permissions (Super Admin only)
     */
    public function getPermissions(Request $request)
    {
        $user = $request->user();

        // Only super admin can view permissions
        if (!$user || !$user->role || $user->role->name !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $permissions = Permission::all();
        } catch (\Exception $e) {
            \Log::error('Failed to fetch permissions', ['error' => $e->getMessage()]);
            // Return an empty permissions array instead of letting a 500 bubble up
            return response()->json([
                'status' => true,
                'permissions' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'permissions' => $permissions
        ]);
    }
}
