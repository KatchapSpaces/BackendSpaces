<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\FloorPlan;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
  public function index()
{
    $user = auth()->user();

    // For testing: if no auth, return empty array
    if (!$user) {
        \Log::info('No auth user for projects, returning empty');
        return response()->json([]);
    }

    \Log::info('Projects fetch by user', [
        'user_id' => $user->id,
        'user_company_id' => $user->company_id,
        'user_role' => $user->role ? $user->role->name : null,
    ]);

    // Check if user has view_project permission
    if (!$user->hasPermission('view_project')) {
        // Allow common organization roles to view projects by default even if permission entry is missing
        $roleName = $user->role ? $user->role->name : null;
        $allowedByRole = ['super_admin', 'admin', 'manager', 'subcontractor', 'user', 'basic'];
        if ($roleName && in_array($roleName, $allowedByRole)) {
            \Log::info('User missing explicit view_project permission but allowed by role', [
                'user_id' => $user->id,
                'user_role' => $roleName,
            ]);
            // proceed — role-based visibility logic will determine projects
        } else {
            \Log::info('User does not have view_project permission and is not in allowed roles', [
                'user_id' => $user->id,
                'user_role' => $roleName,
            ]);
            return response()->json([]);
        }
    }

    // Super admins see only their own projects
    if ($user->hasRole('super_admin')) {
        return Project::where('created_by', $user->id)
            ->with('floorPlans', 'creator', 'assignedAdmin', 'assignedManager', 'siteTeam')
            ->get();
    }

    // Admins see all projects from their super admin ONLY
    if ($user->hasRole('admin')) {
        // Find the super admin who invited this admin
        $superAdminId = Invitation::where('email', $user->email)
            ->where('role', 'admin')
            ->value('invited_by');

        // Admin should only see projects from their super admin
        if (!$superAdminId) {
            return response()->json([]);
        }

        return Project::where('created_by', $superAdminId)
            ->orWhere('created_by', $user->id)
            ->with('floorPlans', 'creator', 'assignedAdmin', 'assignedManager', 'siteTeam')
            ->get();
    }

    // Managers see ONLY projects created by their super admin (not all super admins)
    if ($user->hasRole('manager')) {
        // Find who invited the manager
        $managerInvitation = Invitation::where('email', $user->email)
            ->where('role', 'manager')
            ->first();

        $managerSuperAdminId = null;

        if ($managerInvitation) {
            // Determine the super admin of the manager's organization from the invitation
            $managerInviterUser = User::find($managerInvitation->invited_by);
            if ($managerInviterUser) {
                if ($managerInviterUser->hasRole('super_admin')) {
                    $managerSuperAdminId = $managerInvitation->invited_by;
                } elseif ($managerInviterUser->hasRole('admin')) {
                    $managerSuperAdminId = Invitation::where('email', $managerInviterUser->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                }
            }
        }

        // FALLBACK: if managerInvitation is missing or cannot determine super admin, try company ownership
        if (!$managerSuperAdminId && $user->company_id) {
            $company = \App\Models\Company::find($user->company_id);
            if ($company && $company->created_by_user_id) {
                $companyCreator = User::find($company->created_by_user_id);
                if ($companyCreator) {
                    if ($companyCreator->hasRole('super_admin')) {
                        $managerSuperAdminId = $companyCreator->id;
                    } elseif ($companyCreator->hasRole('admin')) {
                        $managerSuperAdminId = Invitation::where('email', $companyCreator->email)
                            ->where('role', 'admin')
                            ->value('invited_by');
                    }
                }
            }
        }
        // Include projects created by the super admin AND projects created by any admin
        // users that were invited by the same super admin.
        $adminEmails = Invitation::where('invited_by', $managerSuperAdminId)
            ->where('role', 'admin')
            ->pluck('email')
            ->toArray();

        $adminUserIds = User::whereIn('email', $adminEmails)->pluck('id')->toArray();

        $projects = Project::where(function ($query) use ($managerSuperAdminId, $user, $adminUserIds) {
            // Projects created by their super admin
            $query->where('created_by', $managerSuperAdminId)
                // OR projects created by admins in the same org
                ->orWhereIn('created_by', $adminUserIds)
                // OR projects they created themselves
                ->orWhere('created_by', $user->id)
                // OR projects explicitly assigned to them as manager
                ->orWhere('assigned_manager_id', $user->id)
                // OR projects explicitly assigned to them as admin
                ->orWhere('assigned_admin_id', $user->id);
        })
        ->orWhereHas('siteTeam', function ($query) use ($user) {
            // OR projects where they're in the site team
            $query->where('user_id', $user->id);
        })
        ->with('floorPlans', 'creator', 'assignedAdmin', 'assignedManager', 'siteTeam')
        ->get();

        return $projects; 
    }

    // Other users (subcontractor, basic, granular, design_team, etc) - prefer to resolve via invitation but fall back to company
    $userSuperAdminId = null;

    // Try via invitation chain first
    $userInvitation = Invitation::where('email', $user->email)->first();
    if ($userInvitation) {
        $userInviterUser = User::find($userInvitation->invited_by);
        if ($userInviterUser) {
            if ($userInviterUser->hasRole('super_admin')) {
                $userSuperAdminId = $userInviterUser->id;
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
        }
    }

    // Fallback: try company ownership to find the super admin / org owner
    if (!$userSuperAdminId && $user->company_id) {
        $company = \App\Models\Company::find($user->company_id);
        if ($company && $company->created_by_user_id) {
            $companyCreator = User::find($company->created_by_user_id);
            if ($companyCreator) {
                if ($companyCreator->hasRole('super_admin')) {
                    $userSuperAdminId = $companyCreator->id;
                } elseif ($companyCreator->hasRole('admin')) {
                    $userSuperAdminId = Invitation::where('email', $companyCreator->email)
                        ->where('role', 'admin')
                        ->value('invited_by');
                }
            }
        }
    }

    // If we found a super admin, include their projects and admins in that org
    if ($userSuperAdminId) {
        $adminEmails = Invitation::where('invited_by', $userSuperAdminId)
            ->where('role', 'admin')
            ->pluck('email')
            ->toArray();

        $adminUserIds = User::whereIn('email', $adminEmails)->pluck('id')->toArray();

        $projects = Project::where(function ($q) use ($userSuperAdminId, $adminUserIds, $user) {
            $q->where('created_by', $userSuperAdminId)
                ->orWhereIn('created_by', $adminUserIds)
                ->orWhere('created_by', $user->id);
        })
        ->with('floorPlans', 'creator', 'assignedAdmin', 'assignedManager', 'siteTeam')
        ->get();

        return $projects;
    }

    // Fallback: if user belongs to a company, return projects created by users in same company
    if ($user->company_id) {
        $companyUserIds = User::where('company_id', $user->company_id)->pluck('id')->toArray();
        $projects = Project::where(function ($q) use ($companyUserIds, $user) {
            $q->whereIn('created_by', $companyUserIds)
                ->orWhere('created_by', $user->id);
        })->with('creator')->get();

        return $projects;
    }

    // Otherwise no projects found for unknown organization
    return response()->json([]);
}

  public function show(Project $project)
{
    $this->authorize('view', $project);

    $user = auth()->user();
    // Hide full floor plan details for subcontractor/basic/legacy 'user' roles
    if ($user && $user->role && in_array($user->role->name, ['subcontractor', 'basic', 'user'])) {
        // Still include basic assignment info (without exposing full floor plan details)
        return response()->json($project->load('creator', 'assignedAdmin', 'assignedManager'));
    }

    // Load additional relations for richer UI (assigned users and site team)
    return $project->load('floorPlans', 'creator', 'assignedAdmin', 'assignedManager', 'siteTeam');
} 


    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Check if user has create_project permission
        if (!$user->hasPermission('create_project')) {
            return response()->json(['message' => 'You do not have permission to create projects'], 403);
        }

        $request->validate([
            'title'       => 'required|string|max:255',
            'image'       => 'nullable|image',
            'drawings'    => 'nullable|array',
            'drawings.*'  => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        // Store MAIN project image in PUBLIC disk
        $imagePath = $request->file('image')
            ? $request->file('image')->store('projects', 'public')
            : null;

        $project = Project::create([
            'title'        => $request->title,
            'description'  => $request->description,
            'site_number'  => $request->site_number,
            'timezone'     => $request->timezone,
            'address'      => $request->address,
            'location'     => $request->location,
            'measurement'  => $request->measurement,
            'created_by'   => auth()->id(),
            'image_path'   => $imagePath,
        ]);

        // Save floor plan drawings
        if ($request->hasFile('drawings')) {
            foreach ($request->file('drawings') as $file) {
                $project->floorPlans()->create([
                    'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'description' => 'Uploaded drawing',
                    'file_path' => $file->store('floorplans', 'public'),
                    'original_filename' => $file->getClientOriginalName(),
                ]);
            }
        }

        return response()->json($project->load('floorPlans', 'creator'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
       
        $request->validate([
            'title'       => 'sometimes|string|max:255',
            'image'       => 'nullable|image',
            'drawings'    => 'nullable|array',
            'drawings.*'  => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        $project->update($request->only([
            'title',
            'description',
            'site_number',
            'timezone',
            'address',
            'location',
            'measurement',
        ]));

        // Replace main project image
        if ($request->file('image')) {

            // delete old image (public disk)
            if ($project->image_path && Storage::disk('public')->exists($project->image_path)) {
                Storage::disk('public')->delete($project->image_path);
            }

            $project->image_path = $request->file('image')->store('projects', 'public');
            $project->save();
        }

        // Add new floor plans
        if ($request->hasFile('drawings')) {
            foreach ($request->file('drawings') as $file) {
                $project->floorPlans()->create([
                    'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'description' => 'Uploaded drawing',
                    'file_path' => $file->store('floorplans', 'public'),
                    'original_filename' => $file->getClientOriginalName(),
                ]);
            }
        }

        return response()->json($project->load('floorPlans', 'creator'));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
       
        // delete project main image
        if ($project->image_path && Storage::disk('public')->exists($project->image_path)) {
            Storage::disk('public')->delete($project->image_path);
        }

        // delete all floor plan files
        foreach ($project->floorPlans as $fp) {
            if (Storage::disk('public')->exists($fp->file_path)) {
                Storage::disk('public')->delete($fp->file_path);
            }
        }

        $project->floorPlans()->delete();
        $project->delete();

        return response()->json(['message' => 'Project deleted']);
    }
}
