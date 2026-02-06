<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Service::with(['space', 'attributes', 'assignedSubcontractor', 'tasks']);

        // Filter by space if provided
        if ($request->has('space_id')) {
            $query->where('space_id', $request->space_id);
        }

        // Super Admin can see all services
        if (!$user->hasRole('super_admin')) {
            // Regular users see services they're assigned to or have permission to view
            $query->where(function ($q) use ($user) {
                $q->where('assigned_subcontractor_id', $user->id)
                  ->orWhereHas('space.floorPlan.project', function ($projectQuery) use ($user) {
                      $projectQuery->where('created_by', $user->id)
                                   ->orWhere('assigned_admin_id', $user->id)
                                   ->orWhere('assigned_manager_id', $user->id)
                                   ->orWhereHas('siteTeam', function ($teamQuery) use ($user) {
                                       $teamQuery->where('user_id', $user->id);
                                   });
                  });
            });
        }

        $services = $query->get();

        return response()->json($services);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'name' => 'required|string|max:255',
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'icon' => 'nullable|string',
            'assigned_subcontractor_id' => 'nullable|exists:users,id',
        ]);

        $user = Auth::user();

        // Check if user has permission to create services for this space's project
        if (!$user->hasRole('super_admin')) {
            $space = \App\Models\Space::with('floorPlan.project')->find($request->space_id);
            if (!$space ||
                ($space->floorPlan->project->created_by !== $user->id &&
                 $space->floorPlan->project->assigned_admin_id !== $user->id &&
                 !$user->hasPermission('create_service'))) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $service = Service::create($request->only([
            'space_id', 'name', 'x', 'y', 'icon', 'assigned_subcontractor_id'
        ]));

        Log::info("Service created", [
            'service_id' => $service->id,
            'space_id' => $request->space_id,
            'created_by' => $user->id,
            'assigned_subcontractor' => $request->assigned_subcontractor_id
        ]);

        return response()->json($service->load(['space', 'assignedSubcontractor']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        $user = Auth::user();

        // Super Admin can view any service
        if (!$user->hasRole('super_admin')) {
            // Check if user has access to this service
            if ($service->assigned_subcontractor_id !== $user->id &&
                !$service->space->floorPlan->project->siteTeam()->where('user_id', $user->id)->exists() &&
                $service->space->floorPlan->project->created_by !== $user->id &&
                $service->space->floorPlan->project->assigned_admin_id !== $user->id &&
                $service->space->floorPlan->project->assigned_manager_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($service->load(['space.floorPlan.project', 'attributes', 'assignedSubcontractor', 'tasks']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Service $service)
    {
        $user = Auth::user();

        // Super Admin can update any service
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to update this service
            if ($service->assigned_subcontractor_id !== $user->id &&
                $service->space->floorPlan->project->created_by !== $user->id &&
                $service->space->floorPlan->project->assigned_admin_id !== $user->id &&
                !$user->hasPermission('edit_service')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'icon' => 'nullable|string',
            'assigned_subcontractor_id' => 'nullable|exists:users,id',
        ]);

        $oldAssignment = $service->assigned_subcontractor_id;

        $service->update($request->only([
            'name', 'x', 'y', 'icon', 'assigned_subcontractor_id'
        ]));

        Log::info("Service updated", [
            'service_id' => $service->id,
            'updated_by' => $user->id,
            'old_assignment' => $oldAssignment,
            'new_assignment' => $request->assigned_subcontractor_id
        ]);

        return response()->json($service->load(['space', 'assignedSubcontractor']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        $user = Auth::user();

        // Super Admin can delete any service
        if (!$user->hasRole('super_admin')) {
            // Check if user has permission to delete this service
            if ($service->space->floorPlan->project->created_by !== $user->id &&
                $service->space->floorPlan->project->assigned_admin_id !== $user->id &&
                !$user->hasPermission('delete_service')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        Log::info("Service deleted", [
            'service_id' => $service->id,
            'deleted_by' => $user->id
        ]);

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }

    /**
     * Assign subcontractor to service (Super Admin only)
     */
    public function assignSubcontractor(Request $request, Service $service)
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'subcontractor_id' => 'nullable|exists:users,id',
        ]);

        $oldAssignment = $service->assigned_subcontractor_id;

        $service->update(['assigned_subcontractor_id' => $request->subcontractor_id]);

        Log::info("Service subcontractor assigned", [
            'service_id' => $service->id,
            'assigned_by' => $user->id,
            'old_assignment' => $oldAssignment,
            'new_assignment' => $request->subcontractor_id
        ]);

        return response()->json($service->load('assignedSubcontractor'));
    }

    /**
     * Get available subcontractors for assignment (Super Admin only)
     */
    public function getAssignableSubcontractors()
    {
        $user = Auth::user();

        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $subcontractors = User::where('status', 'active')
            ->whereHas('role', function ($query) {
                $query->where('name', 'subcontractor');
            })
            ->with('role')
            ->get(['id', 'name', 'email', 'role_id']);

        return response()->json($subcontractors);
    }
}
