<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Task::with(['assignedUser', 'project', 'service.space.floorPlan.project', 'creator']);

        // Filter by project if provided
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by service if provided
        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user if provided
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Super Admin can see all tasks
        if (!$user->hasRole('super_admin')) {
            // Regular users see tasks they're assigned to, created, or have access to via projects/services
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id)
                  ->orWhereHas('project', function ($projectQuery) use ($user) {
                      $projectQuery->where('created_by', $user->id)
                                   ->orWhere('assigned_admin_id', $user->id)
                                   ->orWhere('assigned_manager_id', $user->id)
                                   ->orWhereHas('siteTeam', function ($teamQuery) use ($user) {
                                       $teamQuery->where('user_id', $user->id);
                                   });
                  })
                  ->orWhereHas('service.space.floorPlan.project', function ($projectQuery) use ($user) {
                      $projectQuery->where('created_by', $user->id)
                                   ->orWhere('assigned_admin_id', $user->id)
                                   ->orWhere('assigned_manager_id', $user->id)
                                   ->orWhereHas('siteTeam', function ($teamQuery) use ($user) {
                                       $teamQuery->where('user_id', $user->id);
                                   });
                  });
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
            'service_id' => 'nullable|exists:services,id',
            'due_date' => 'nullable|date|after:today',
        ]);

        $user = Auth::user();

        // Validate that project_id and service_id are not both null
        if (!$request->project_id && !$request->service_id) {
            return response()->json(['message' => 'Task must be assigned to either a project or service'], 422);
        }

        // Check permissions
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to create tasks for the associated project/service
            if ($request->project_id) {
                $project = \App\Models\Project::find($request->project_id);
                if (!$project ||
                    ($project->created_by !== $user->id &&
                     $project->assigned_admin_id !== $user->id &&
                     $project->assigned_manager_id !== $user->id &&
                     !$project->siteTeam()->where('user_id', $user->id)->exists() &&
                     !$user->hasPermission('create_task'))) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }

            if ($request->service_id) {
                $service = \App\Models\Service::with('space.floorPlan.project')->find($request->service_id);
                if (!$service ||
                    ($service->assigned_subcontractor_id !== $user->id &&
                     $service->space->floorPlan->project->created_by !== $user->id &&
                     $service->space->floorPlan->project->assigned_admin_id !== $user->id &&
                     $service->space->floorPlan->project->assigned_manager_id !== $user->id &&
                     !$service->space->floorPlan->project->siteTeam()->where('user_id', $user->id)->exists() &&
                     !$user->hasPermission('create_task'))) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }
        }

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'pending',
            'priority' => $request->priority ?? 'medium',
            'assigned_to' => $request->assigned_to,
            'project_id' => $request->project_id,
            'service_id' => $request->service_id,
            'created_by' => $user->id,
            'due_date' => $request->due_date,
        ]);

        Log::info("Task created", [
            'task_id' => $task->id,
            'created_by' => $user->id,
            'assigned_to' => $request->assigned_to,
            'project_id' => $request->project_id,
            'service_id' => $request->service_id
        ]);

        return response()->json($task->load(['assignedUser', 'project', 'service.space.floorPlan.project', 'creator']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $user = Auth::user();

        // Super Admin can view any task
        if (!$user->hasRole('super_admin')) {
            // Check if user has access to this task
            if ($task->assigned_to !== $user->id &&
                $task->created_by !== $user->id &&
                !$this->userHasAccessToTaskProject($user, $task) &&
                !$user->hasPermission('view_task')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($task->load(['assignedUser', 'project', 'service.space.floorPlan.project', 'creator']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $user = Auth::user();

        // Super Admin can update any task
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to update this task
            if ($task->assigned_to !== $user->id &&
                $task->created_by !== $user->id &&
                !$this->userHasAccessToTaskProject($user, $task) &&
                !$user->hasPermission('edit_task')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $oldAssignment = $task->assigned_to;
        $oldStatus = $task->status;

        $task->update($request->only([
            'title', 'description', 'status', 'priority', 'assigned_to', 'due_date'
        ]));

        Log::info("Task updated", [
            'task_id' => $task->id,
            'updated_by' => $user->id,
            'old_assignment' => $oldAssignment,
            'new_assignment' => $request->assigned_to,
            'old_status' => $oldStatus,
            'new_status' => $request->status
        ]);

        return response()->json($task->load(['assignedUser', 'project', 'service.space.floorPlan.project', 'creator']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $user = Auth::user();

        // Super Admin can delete any task
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to delete this task
            if ($task->created_by !== $user->id &&
                !$this->userHasAccessToTaskProject($user, $task) &&
                !$user->hasPermission('delete_task')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        Log::info("Task deleted", [
            'task_id' => $task->id,
            'deleted_by' => $user->id
        ]);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    /**
     * Assign user to task (Admin or authorized users)
     */
    public function assignUser(Request $request, Task $task)
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin') && !$user->hasPermission('assign_task')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $oldAssignment = $task->assigned_to;

        $task->update(['assigned_to' => $request->user_id]);

        Log::info("Task user assigned", [
            'task_id' => $task->id,
            'assigned_by' => $user->id,
            'old_assignment' => $oldAssignment,
            'new_assignment' => $request->user_id
        ]);

        return response()->json($task->load('assignedUser'));
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, Task $task)
    {
        $user = Auth::user();

        // Super Admin can update any task status
        if (!$user->hasRole('super_admin')) {
            // Check if user is assigned to the task or has permission
            if ($task->assigned_to !== $user->id &&
                !$this->userHasAccessToTaskProject($user, $task) &&
                !$user->hasPermission('edit_task')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        $oldStatus = $task->status;

        $task->update(['status' => $request->status]);

        Log::info("Task status updated", [
            'task_id' => $task->id,
            'updated_by' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $request->status
        ]);

        return response()->json($task->load(['assignedUser', 'project', 'service.space.floorPlan.project', 'creator']));
    }

    /**
     * Get available users for task assignment (Super Admin or authorized users)
     */
    public function getAssignableUsers()
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin') && !$user->hasPermission('assign_task')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::where('status', 'active')
            ->with('role')
            ->get(['id', 'name', 'email', 'role_id']);

        return response()->json($users);
    }

    /**
     * Helper method to check if user has access to task's project
     */
    private function userHasAccessToTaskProject($user, $task)
    {
        if ($task->project) {
            return $task->project->created_by === $user->id ||
                   $task->project->assigned_admin_id === $user->id ||
                   $task->project->assigned_manager_id === $user->id ||
                   $task->project->siteTeam()->where('user_id', $user->id)->exists();
        }

        if ($task->service) {
            $project = $task->service->space->floorPlan->project;
            return $project->created_by === $user->id ||
                   $project->assigned_admin_id === $user->id ||
                   $project->assigned_manager_id === $user->id ||
                   $project->siteTeam()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
