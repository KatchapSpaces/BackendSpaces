<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteTeam;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiteTeamController extends Controller
{
    public function index(Request $request, $projectId)
    {
        $user = auth()->user();
        
        // Super admin, admin, and manager can view site teams
        if (!$user || !$user->role || !in_array($user->role->name, ['super_admin', 'admin', 'manager'])) {
            return response()->json(['message' => 'You do not have permission to view site teams. Only super_admin, admin, or manager may view/manage site teams.'], 403);
        }

        // Ensure project belongs to the same organization as the requesting user
        $project = \App\Models\Project::find($projectId);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        // Organisation check helper
        $authOrgSuperAdminId = $this->getOrganizationSuperAdminId($user);
        $projectCreatorSuperAdminId = $this->getOrganizationSuperAdminId($project->creator);

        if ($authOrgSuperAdminId !== $projectCreatorSuperAdminId) {
            return response()->json(['message' => 'You do not have access to this project'], 403);
        }

        // Super admin and admin can see all site teams for the project
        if (in_array($user->role->name, ['super_admin', 'admin'])) {
            $siteTeams = SiteTeam::where('project_id', $projectId)
                ->with('user')
                ->get()
                ->map(function ($siteTeam) {
                    // Ensure user is loaded, fallback if null
                    $user = $siteTeam->user;
                    if (!$user) {
                        \Log::warning("SiteTeam {$siteTeam->id} has no associated user!");
                        return null;
                    }
                    
                    return [
                        'id' => $user->id, // backward-compatible user id
                        'site_team_id' => $siteTeam->id, // site_team table id (for assignments)
                        'user_id' => $user->id,
                        'name' => $user->name ?? "User {$user->id}",
                        'email' => $user->email,
                        'role' => $siteTeam->role,
                        'company' => $user->company->name ?? null,
                        'status' => $user->status,
                        'created_at' => $siteTeam->created_at,
                        'updated_at' => $siteTeam->updated_at,
                    ];
                })
                ->filter(); // Remove null entries
        } else {
            // Manager can view:
            // 1. Site teams they created
            // 2. Site teams with roles below them (subcontractor, basic, granular, design_team)
            // 3. Site teams they are assigned to (user_id = manager's id)
            $siteTeams = SiteTeam::where('project_id', $projectId)
                ->where(function($q) use ($user) {
                    // 1. Created by this manager
                    $q->where('created_by', $user->id)
                    // 2. OR roles below manager (subcontractor, basic, granular, design_team)
                      ->orWhereIn('role', ['subcontractor', 'basic', 'granular', 'design_team'])
                    // 3. OR assigned to this manager
                      ->orWhere('user_id', $user->id);
                })
                ->with('user')
                ->get()
                ->map(function ($siteTeam) {
                    // Ensure user is loaded, fallback if null
                    $user = $siteTeam->user;
                    if (!$user) {
                        \Log::warning("SiteTeam {$siteTeam->id} has no associated user!");
                        return null;
                    }
                    
                    return [
                        'id' => $user->id, // backward-compatible user id
                        'site_team_id' => $siteTeam->id, // site_team table id (for assignments)
                        'user_id' => $user->id,
                        'name' => $user->name ?? "User {$user->id}",
                        'email' => $user->email,
                        'role' => $siteTeam->role,
                        'company' => $user->company->name ?? null,
                        'status' => $user->status,
                        'created_at' => $siteTeam->created_at,
                        'updated_at' => $siteTeam->updated_at,
                    ];
                })
                ->filter(); // Remove null entries
        }

        return response()->json($siteTeams);
    }

    public function store(Request $request, $projectId)
    {
        $user = auth()->user();
        
        // Super admin, admin, and manager can create site team members
        if (!$user || !$user->role || !in_array($user->role->name, ['super_admin', 'admin', 'manager'])) {
            return response()->json(['message' => 'You do not have permission to create site team members. Only super_admin, admin, or manager may manage site team members.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email',
            'role' => 'required|string',
            'password' => 'sometimes|string|min:6',
            'company_id' => 'required|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // CHECK 1: Verify company belongs to the user's organization
        $company = \App\Models\Company::find($request->company_id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Super admin can only use their own companies
        if ($user->hasRole('super_admin') && $company->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'You can only use companies from your organization'], 403);
        }

        // Admin can only use companies from their super admin
        if ($user->hasRole('admin')) {
            $superAdminId = Invitation::where('email', $user->email)
                ->where('role', 'admin')
                ->value('invited_by');
            
            if (!$superAdminId || $company->created_by_user_id !== $superAdminId) {
                return response()->json(['message' => 'You can only use companies from your organization'], 403);
            }
        }

        // Manager can only use companies from their super admin/admin
        if ($user->hasRole('manager')) {
            $creatingUser = User::find($company->created_by_user_id);
            if (!$creatingUser || !in_array($creatingUser->role->name, ['super_admin', 'admin'])) {
                return response()->json(['message' => 'Invalid company'], 403);
            }
        }

        // Managers are only allowed to create subcontractor or basic site team members
        if ($user->hasRole('manager')) {
            if (!in_array($request->role, ['subcontractor', 'basic'])) {
                return response()->json(['message' => 'Managers can only create site team members with roles subcontractor or basic'], 403);
            }
        }

        // CHECK 2: Verify user is invited with the matching role
        // Allow both pending and accepted invitations
        // Treat 'basic' and legacy 'user' as equivalent
        $roleCandidates = $request->role === 'basic' ? ['basic', 'user'] : [$request->role];

        $invitation = Invitation::where('email', $request->email)
            ->whereIn('role', $roleCandidates)
            ->first();

        // If there's no invitation, allow if an existing user exists with the same (equivalent) role
        $existingUser = User::where('email', $request->email)->first();
        $invitationFromExistingUser = false;

        if (!$invitation) {
            if ($existingUser) {
                // If existing user's role matches one of roleCandidates, allow (use existing user)
                if ($existingUser->role && in_array($existingUser->role->name, $roleCandidates)) {
                    $invitationFromExistingUser = true;
                    // proceed without an Invitation object; some later checks are skipped when no invitation exists
                } else {
                    // Check if invitation exists but with different role
                    $existingInvitation = Invitation::where('email', $request->email)->first();
                    if ($existingInvitation) {
                        // User invited but with different role
                        return response()->json([
                            'message' => 'User with this email is not invited as ' . $request->role,
                            'error' => 'invited_with_different_role',
                            'invited_role' => $existingInvitation->role
                        ], 422);
                    }

                    // No matching invitation or role mismatch on existing user
                    return response()->json([
                        'message' => 'User with this email is not invited',
                        'error' => 'not_invited'
                    ], 422);
                }
            } else {
                // User not invited and does not exist
                return response()->json([
                    'message' => 'User with this email is not invited',
                    'error' => 'not_invited'
                ], 422);
            }
        }

        // CHECK 3: Verify invitation belongs to the authenticated user's organization
        // These checks require an Invitation object; if we are operating on an existing user without an Invitation
        // we skip invitation-based validations and rely on company ownership + role match checks done earlier.
        if ($invitation) {
            if ($user->hasRole('super_admin')) {
                // Super admin must have invited this user
                if ($invitation->invited_by !== $user->id) {
                    return response()->json(['message' => 'You can only use invitations you created'], 403);
                }
            }

            if ($user->hasRole('admin')) {
                // Admin's inviter (super admin) must match the company's creator
                $superAdminId = Invitation::where('email', $user->email)
                    ->where('role', 'admin')
                    ->value('invited_by');
                
                if ($invitation->invited_by !== $superAdminId) {
                    return response()->json(['message' => 'You can only use invitations from your organization'], 403);
                }
            }

            if ($user->hasRole('manager')) {
                // Manager must be invited by someone in their organization
                // Find who invited the manager
                $managerInviter = Invitation::where('email', $user->email)
                    ->where('role', 'manager')
                    ->value('invited_by');

                if (!$managerInviter) {
                    return response()->json(['message' => 'Manager invitation not found'], 403);
                }

                // Get manager's inviter user to find their organization
                $managerInviterUser = User::find($managerInviter);
                if (!$managerInviterUser) {
                    return response()->json(['message' => 'Invalid manager inviter'], 403);
                }
                
                // Determine the super admin of the manager's organization
                $managerSuperAdminId = null;
                
                if ($managerInviterUser->hasRole('super_admin')) {
                    $managerSuperAdminId = $managerInviter; // Manager's inviter is the super admin
                } elseif ($managerInviterUser->hasRole('admin')) {
                    // Manager was invited by admin - get the admin's super admin
                    $managerSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                }
                
                if (!$managerSuperAdminId) {
                    return response()->json(['message' => 'Cannot determine manager organization'], 403);
                }

                // Verify the person being invited was also invited by someone in the same organization
                $inviterUser = User::find($invitation->invited_by);
                if (!$inviterUser) {
                    return response()->json(['message' => 'Invalid invitation inviter'], 403);
                }

                $invitationSuperAdminId = null;
                if ($inviterUser->hasRole('super_admin')) {
                    $invitationSuperAdminId = $invitation->invited_by;
                } elseif ($inviterUser->hasRole('admin')) {
                    $invitationSuperAdminId = Invitation::where('email', $inviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                }

                // Manager can only use invitations from their own super admin's organization
                if ($invitationSuperAdminId !== $managerSuperAdminId) {
                    return response()->json([
                        'message' => 'You can only manage users invited by your organization',
                        'error' => 'cross_organization_access_denied'
                    ], 403);
                }
            }
        }

        // CHECK 3b: Validate all other roles (subcontractor, basic, granular, design_team)
        $otherRoles = ['subcontractor', 'basic', 'granular', 'design_team'];
        if (in_array($user->role->name, $otherRoles)) {
            // Non-management roles must be invited by someone in their organization
            // Find who invited this user
            $userInvitation = Invitation::where('email', $user->email)
                ->where('role', $user->role->name)
                ->first();

            if (!$userInvitation) {
                return response()->json(['message' => 'User invitation not found'], 403);
            }

            // Get the organization's super admin
            $userInviterUser = User::find($userInvitation->invited_by);
            if (!$userInviterUser) {
                return response()->json(['message' => 'Invalid user inviter'], 403);
            }

            $userSuperAdminId = null;
            if ($userInviterUser->hasRole('super_admin')) {
                $userSuperAdminId = $userInvitation->invited_by;
            } elseif ($userInviterUser->hasRole('admin')) {
                $userSuperAdminId = Invitation::where('email', $userInviterUser->email)
                    ->where('role', 'admin')
                    ->value('invited_by');
            } elseif ($userInviterUser->hasRole('manager')) {
                $managerInvitation = Invitation::where('email', $userInviterUser->email)
                    ->where('role', 'manager')
                    ->first();
                
                if ($managerInvitation) {
                    $managerInviterUser = User::find($managerInvitation->invited_by);
                    if ($managerInviterUser && $managerInviterUser->hasRole('admin')) {
                        $userSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                            ->where('role', 'admin')
                            ->value('invited_by');
                    } else {
                        $userSuperAdminId = $managerInvitation->invited_by;
                    }
                }
            }

            if (!$userSuperAdminId) {
                return response()->json(['message' => 'Cannot determine user organization'], 403);
            }

            // Verify the person being invited was also invited by someone in the same organization
            $inviterUser = User::find($invitation->invited_by);
            if (!$inviterUser) {
                return response()->json(['message' => 'Invalid invitation inviter'], 403);
            }

            $invitationSuperAdminId = null;
            if ($inviterUser->hasRole('super_admin')) {
                $invitationSuperAdminId = $invitation->invited_by;
            } elseif ($inviterUser->hasRole('admin')) {
                $invitationSuperAdminId = Invitation::where('email', $inviterUser->email)
                    ->where('role', 'admin')
                    ->value('invited_by');
            } elseif ($inviterUser->hasRole('manager')) {
                $managerInvitation = Invitation::where('email', $inviterUser->email)
                    ->where('role', 'manager')
                    ->first();
                
                if ($managerInvitation) {
                    $managerInviterUser = User::find($managerInvitation->invited_by);
                    if ($managerInviterUser && $managerInviterUser->hasRole('admin')) {
                        $invitationSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                            ->where('role', 'admin')
                            ->value('invited_by');
                    } else {
                        $invitationSuperAdminId = $managerInvitation->invited_by;
                    }
                }
            }

            // User can only use invitations from their own super admin's organization
            if ($invitationSuperAdminId !== $userSuperAdminId) {
                return response()->json([
                    'message' => 'You can only manage users from your organization',
                    'error' => 'cross_organization_access_denied'
                ], 403);
            }
        }

        // Check if user already exists
        $existingUser = User::where('email', $request->email)->first();
        
        // CHECK 4: Verify invited user belongs to this company (only if user already exists)
        if ($existingUser && $existingUser->company_id !== $request->company_id) {
            // User exists but belongs to different company - not allowed
            $userCompany = \App\Models\Company::find($existingUser->company_id);
            $requestedCompany = \App\Models\Company::find($request->company_id);
            
            return response()->json([
                'message' => 'User belongs to ' . ($userCompany?->name ?? 'another company') . ', not ' . ($requestedCompany?->name ?? 'the requested company'),
                'error' => 'user_company_mismatch'
            ], 403);
        }

        // Resolve the Role model using equivalent role names (basic <-> user)
        $role = \App\Models\Role::whereIn('name', $roleCandidates)->first();
        if (!$role) {
            return response()->json(['errors' => ['role' => 'Invalid role']], 422);
        }
        
        if ($existingUser) {
            // User already exists, check if already added to this project
            $alreadyInSiteTeam = \App\Models\SiteTeam::where('user_id', $existingUser->id)
                ->where('project_id', $projectId)
                ->exists();
            
            if ($alreadyInSiteTeam) {
                return response()->json([
                    'message' => 'This user is already a member of this project',
                    'error' => 'already_in_site_team'
                ], 409);
            }
            
            // User already exists, just add to site team
            $newUser = $existingUser;
            // Update role if needed
            if ($existingUser->role_id !== $role->id) {
                $existingUser->update(['role_id' => $role->id]);
            }
        } else {
            // Create new user
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password ?: 'default123'),
                'company_id' => $request->company_id,
                'role_id' => $role->id,
            ]);
        }

        // Mark invitation as accepted (only if an Invitation record exists)
        if ($invitation) {
            $invitation->update(['accepted_at' => now()]);
        }

        // Assign to project - use the resolved role name (so basic can be stored as 'user' if mapped)
        SiteTeam::create([
            'user_id' => $newUser->id,
            'project_id' => $projectId,
            'role' => $role->name,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'id' => $newUser->id,
            'name' => $newUser->name,
            'email' => $newUser->email,
            'role' => $role->name,
            'status' => $newUser->status,
            'created_at' => $newUser->created_at,
            'updated_at' => $newUser->updated_at,
        ], 201);
    }

    // Helper to resolve the super admin id for a given user
    private function getOrganizationSuperAdminId($user)
    {
        if (!$user || !$user->role) return null;

        if ($user->hasRole('super_admin')) return $user->id;

        if ($user->hasRole('admin')) {
            return Invitation::where('email', $user->email)
                ->where('role', 'admin')
                ->value('invited_by');
        }

        if ($user->hasRole('manager')) {
            $inv = Invitation::where('email', $user->email)->where('role', 'manager')->first();
            if (!$inv) return null;
            $inviter = User::find($inv->invited_by);
            if (!$inviter) return null;
            if ($inviter->hasRole('super_admin')) return $inv->invited_by;
            if ($inviter->hasRole('admin')) {
                return Invitation::where('email', $inviter->email)->where('role', 'admin')->value('invited_by');
            }
            return null;
        }

        // other roles
        $inv = Invitation::where('email', $user->email)->first();
        if (!$inv) return null;
        $inviter = User::find($inv->invited_by);
        if (!$inviter) return null;
        if ($inviter->hasRole('super_admin')) return $inv->invited_by;
        if ($inviter->hasRole('admin')) return Invitation::where('email', $inviter->email)->where('role', 'admin')->value('invited_by');

        return null;
    }

    public function update(Request $request, $projectId, $userId)
    {
        $authUser = auth()->user();
        
        // Super admin, admin, and manager can update site team members
        if (!$authUser || !$authUser->role || !in_array($authUser->role->name, ['super_admin', 'admin', 'manager'])) {
            return response()->json(['message' => 'You do not have permission to update site team members. Only super_admin, admin, or manager may manage site team members.'], 403);
        }

        // Check if this user has permission to update this site team member
        $siteTeam = SiteTeam::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if (!$siteTeam) {
            return response()->json(['message' => 'Site team member not found'], 404);
        }
        
        // Managers can only update their own created site teams
        if ($authUser->role->name === 'manager' && $siteTeam->created_by !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // ORGANIZATION VALIDATION: Verify auth user can manage this user
        $targetUser = User::find($userId);
        if (!$targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Determine who created/invited the target user
        $targetUserInvitation = Invitation::where('email', $targetUser->email)->first();
        if (!$targetUserInvitation) {
            return response()->json(['message' => 'Target user invitation not found'], 403);
        }

        // Get target user's super admin
        $targetInviterUser = User::find($targetUserInvitation->invited_by);
        if (!$targetInviterUser) {
            return response()->json(['message' => 'Target user inviter not found'], 403);
        }

        $targetUserSuperAdminId = null;
        if ($targetInviterUser->hasRole('super_admin')) {
            $targetUserSuperAdminId = $targetUserInvitation->invited_by;
        } elseif ($targetInviterUser->hasRole('admin')) {
            $targetUserSuperAdminId = Invitation::where('email', $targetInviterUser->email)
                ->where('role', 'admin')
                ->value('invited_by');
        } elseif ($targetInviterUser->hasRole('manager')) {
            $managerInvitation = Invitation::where('email', $targetInviterUser->email)
                ->where('role', 'manager')
                ->first();
            
            if ($managerInvitation) {
                $managerInviterUser = User::find($managerInvitation->invited_by);
                if ($managerInviterUser && $managerInviterUser->hasRole('admin')) {
                    $targetUserSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                } else {
                    $targetUserSuperAdminId = $managerInvitation->invited_by;
                }
            }
        }

        if (!$targetUserSuperAdminId) {
            return response()->json(['message' => 'Cannot determine target user organization'], 403);
        }

        // Get auth user's super admin
        $authUserSuperAdminId = null;
        if ($authUser->hasRole('super_admin')) {
            $authUserSuperAdminId = $authUser->id;
        } elseif ($authUser->hasRole('admin')) {
            $authUserSuperAdminId = Invitation::where('email', $authUser->email)
                ->where('role', 'admin')
                ->value('invited_by');
        } elseif ($authUser->hasRole('manager')) {
            $authUserInvitation = Invitation::where('email', $authUser->email)
                ->where('role', 'manager')
                ->first();
            
            if ($authUserInvitation) {
                $authUserInviterUser = User::find($authUserInvitation->invited_by);
                if ($authUserInviterUser && $authUserInviterUser->hasRole('admin')) {
                    $authUserSuperAdminId = Invitation::where('email', $authUserInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                } else {
                    $authUserSuperAdminId = $authUserInvitation->invited_by;
                }
            }
        }

        if (!$authUserSuperAdminId) {
            return response()->json(['message' => 'Cannot determine your organization'], 403);
        }

        // Verify both users belong to the same super admin organization
        if ($targetUserSuperAdminId !== $authUserSuperAdminId) {
            return response()->json([
                'message' => 'You can only manage roles for users in your organization',
                'error' => 'cross_organization_role_management'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'role' => 'sometimes|string',
            'password' => 'sometimes|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only(['name', 'email']);
        if ($request->role) {
            $roleCandidates = $request->role === 'basic' ? ['basic', 'user'] : [$request->role];
            $role = \App\Models\Role::whereIn('name', $roleCandidates)->first();
            if (!$role) {
                return response()->json(['errors' => ['role' => 'Invalid role']], 422);
            }
            $updateData['role_id'] = $role->id;
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update($updateData);
        if ($request->password) {
            $user->password = bcrypt($request->password);
            $user->save();
        }

        if ($request->role) {
            // Update site team stored role to the resolved role name
            $siteTeam->update(['role' => $role->name]);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->role ? $role->name : $siteTeam->role,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    public function destroy(Request $request, $projectId, $userId)
    {
        $authUser = auth()->user();
        
        // Super admin, admin, and manager can delete site team members
        if (!$authUser || !$authUser->role || !in_array($authUser->role->name, ['super_admin', 'admin', 'manager'])) {
            return response()->json(['message' => 'You do not have permission to delete site team members. Only super_admin, admin, or manager may manage site team members.'], 403);
        }

        // Get the site team member
        $siteTeam = SiteTeam::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if (!$siteTeam) {
            return response()->json(['message' => 'Site team member not found'], 404);
        }
        
        // Managers can only delete their own created site teams
        if ($authUser->role->name === 'manager' && $siteTeam->created_by !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // ORGANIZATION VALIDATION: Verify auth user can delete this user
        $targetUser = User::find($userId);
        if (!$targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Determine who created/invited the target user
        $targetUserInvitation = Invitation::where('email', $targetUser->email)->first();
        if (!$targetUserInvitation) {
            return response()->json(['message' => 'Target user invitation not found'], 403);
        }

        // Get target user's super admin
        $targetInviterUser = User::find($targetUserInvitation->invited_by);
        if (!$targetInviterUser) {
            return response()->json(['message' => 'Target user inviter not found'], 403);
        }

        $targetUserSuperAdminId = null;
        if ($targetInviterUser->hasRole('super_admin')) {
            $targetUserSuperAdminId = $targetUserInvitation->invited_by;
        } elseif ($targetInviterUser->hasRole('admin')) {
            $targetUserSuperAdminId = Invitation::where('email', $targetInviterUser->email)
                ->where('role', 'admin')
                ->value('invited_by');
        } elseif ($targetInviterUser->hasRole('manager')) {
            $managerInvitation = Invitation::where('email', $targetInviterUser->email)
                ->where('role', 'manager')
                ->first();
            
            if ($managerInvitation) {
                $managerInviterUser = User::find($managerInvitation->invited_by);
                if ($managerInviterUser && $managerInviterUser->hasRole('admin')) {
                    $targetUserSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                } else {
                    $targetUserSuperAdminId = $managerInvitation->invited_by;
                }
            }
        }

        if (!$targetUserSuperAdminId) {
            return response()->json(['message' => 'Cannot determine target user organization'], 403);
        }

        // Get auth user's super admin
        $authUserSuperAdminId = null;
        if ($authUser->hasRole('super_admin')) {
            $authUserSuperAdminId = $authUser->id;
        } elseif ($authUser->hasRole('admin')) {
            $authUserSuperAdminId = Invitation::where('email', $authUser->email)
                ->where('role', 'admin')
                ->value('invited_by');
        } elseif ($authUser->hasRole('manager')) {
            $authUserInvitation = Invitation::where('email', $authUser->email)
                ->where('role', 'manager')
                ->first();
            
            if ($authUserInvitation) {
                $authUserInviterUser = User::find($authUserInvitation->invited_by);
                if ($authUserInviterUser && $authUserInviterUser->hasRole('admin')) {
                    $authUserSuperAdminId = Invitation::where('email', $authUserInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                } else {
                    $authUserSuperAdminId = $authUserInvitation->invited_by;
                }
            }
        }

        if (!$authUserSuperAdminId) {
            return response()->json(['message' => 'Cannot determine your organization'], 403);
        }

        // Verify both users belong to the same super admin organization
        if ($targetUserSuperAdminId !== $authUserSuperAdminId) {
            return response()->json([
                'message' => 'You can only delete users from your organization',
                'error' => 'cross_organization_deletion'
            ], 403);
        }

        $siteTeam->delete();

        return response()->json(['message' => 'User removed from site team']);
    }
}
