<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        // Super Admin can see all projects
        if ($user->hasRole('super_admin')) {
            $projects = Project::with(['creator', 'assignedAdmin', 'assignedManager', 'floorPlans', 'tasks'])->get();
        } else {
            // Regular users see projects they created, are assigned to as admin/manager, or are part of the site team
            $projects = Project::where('created_by', $user->id)
                ->orWhere('assigned_admin_id', $user->id)
                ->orWhere('assigned_manager_id', $user->id)
                ->orWhereHas('siteTeam', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['creator', 'assignedAdmin', 'assignedManager', 'floorPlans', 'tasks'])
                ->get();
        }

        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'site_number' => 'nullable|string',
            'timezone' => 'nullable|string',
            'address' => 'nullable|string',
            'location' => 'nullable|string',
            'measurement' => 'nullable|string',
            'image_path' => 'nullable|string',
            'assigned_admin_id' => 'nullable|exists:users,id',
            'assigned_manager_id' => 'nullable|exists:users,id',
        ]);

        $user = Auth::user();

        $project = Project::create([
            'title' => $request->title,
            'description' => $request->description,
            'site_number' => $request->site_number,
            'timezone' => $request->timezone,
            'address' => $request->address,
            'location' => $request->location,
            'measurement' => $request->measurement,
            'image_path' => $request->image_path,
            'created_by' => $user->id,
            'assigned_admin_id' => $request->assigned_admin_id,
            'assigned_manager_id' => $request->assigned_manager_id,
        ]);

        Log::info("Project created", [
            'project_id' => $project->id,
            'created_by' => $user->id,
            'assigned_admin' => $request->assigned_admin_id,
            'assigned_manager' => $request->assigned_manager_id
        ]);

        return response()->json($project->load(['creator', 'assignedAdmin', 'assignedManager']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $user = Auth::user();

        // Super Admin can view any project
        if (!$user->hasRole('super_admin')) {
            // Check if user has access to this project
            if ($project->created_by !== $user->id &&
                $project->assigned_admin_id !== $user->id &&
                $project->assigned_manager_id !== $user->id &&
                !$project->siteTeam()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($project->load(['creator', 'assignedAdmin', 'assignedManager', 'floorPlans', 'tasks', 'siteTeam.user']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $user = Auth::user();

        // Super Admin can update any project
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to update this project
            if ($project->created_by !== $user->id &&
                $project->assigned_admin_id !== $user->id &&
                !$user->hasPermission('edit_project')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'site_number' => 'nullable|string',
            'timezone' => 'nullable|string',
            'address' => 'nullable|string',
            'location' => 'nullable|string',
            'measurement' => 'nullable|string',
            'image_path' => 'nullable|string',
            'assigned_admin_id' => 'nullable|exists:users,id',
            'assigned_manager_id' => 'nullable|exists:users,id',
        ]);

        $oldAssignments = [
            'super_admin' => $project->assigned_admin_id,
            'manager' => $project->assigned_manager_id
        ];

        $project->update($request->only([
            'title', 'description', 'site_number', 'timezone', 'address',
            'location', 'measurement', 'image_path', 'assigned_admin_id', 'assigned_manager_id'
        ]));

        Log::info("Project updated", [
            'project_id' => $project->id,
            'updated_by' => $user->id,
            'old_assignments' => $oldAssignments,
            'new_assignments' => [
                'super_admin' => $request->assigned_admin_id,
                'manager' => $request->assigned_manager_id
            ]
        ]);

        return response()->json($project->load(['creator', 'assignedAdmin', 'assignedManager']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $user = Auth::user();

        // Super Admin can delete any project
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to delete this project
            if ($project->created_by !== $user->id && !$user->hasPermission('delete_project')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        Log::info("Project deleted", [
            'project_id' => $project->id,
            'deleted_by' => $user->id
        ]);

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Assign users to project (Super Admin only)
     */
    public function assignUsers(Request $request, Project $project)
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'admin_id' => 'nullable|exists:users,id',
            'manager_id' => 'nullable|exists:users,id',
            'team_user_ids' => 'nullable|array',
            'team_user_ids.*' => 'exists:users,id',
        ]);

        $oldAssignments = [
            'super_admin' => $project->assigned_admin_id,
            'manager' => $project->assigned_manager_id,
            'team' => $project->siteTeam->pluck('user_id')->toArray()
        ];

        // Update admin and manager assignments
        $project->update([
            'assigned_admin_id' => $request->admin_id,
            'assigned_manager_id' => $request->manager_id,
        ]);

        // Update site team
        if ($request->has('team_user_ids')) {
            $project->siteTeam()->delete(); // Remove existing team members
            foreach ($request->team_user_ids as $userId) {
                $project->siteTeam()->create(['user_id' => $userId]);
            }
        }

        Log::info("Project users assigned", [
            'project_id' => $project->id,
            'assigned_by' => $user->id,
            'old_assignments' => $oldAssignments,
            'new_assignments' => [
                'super_admin' => $request->admin_id,
                'manager' => $request->manager_id,
                'team' => $request->team_user_ids ?? []
            ]
        ]);

        return response()->json($project->load(['assignedAdmin', 'assignedManager', 'siteTeam.user']));
    }

    /**
     * Get available users for assignment (Super Admin only)
     */
    public function getAssignableUsers()
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::where('status', 'active')
            ->with('role')
            ->get(['id', 'name', 'email', 'role_id']);

        return response()->json($users);
    }
}
