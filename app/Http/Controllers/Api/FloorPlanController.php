<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\FloorPlan;
use Illuminate\Support\Facades\Storage;

class FloorPlanController extends Controller
{
    // List all floorplans for a project
    public function index(Project $project)
    {
        $user = auth()->user();
        if ($user && $user->role && in_array($user->role->name, ['subcontractor', 'basic', 'user'])) {
            return response()->json(['message' => 'You do not have permission to view floor plans. Only super_admin, admin, or manager may access floor plans.'], 403);
        }

        return response()->json($project->floorPlans);
    }

    // Upload a new floorplan
    public function store(Request $request, Project $project)
    {
        $user = auth()->user();

        if ($user && $user->role && in_array($user->role->name, ['subcontractor', 'basic', 'user'])) {
            return response()->json(['message' => 'You do not have permission to manage floor plans. Only super_admin, admin, or manager may manage floor plans.'], 403);
        }

        // Verify user is allowed to access this project (will use ProjectPolicy -> view)
        try {
            $this->authorize('view', $project);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized: You cannot create floorplans for this project'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|file|mimes:pdf|max:10240', // max 10MB
            'rotation_angle' => 'nullable|numeric',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('floorplans', 'public');

        $floorplan = $project->floorPlans()->create([
            'title' => $request->title,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'rotation_angle' => $request->rotation_angle ?? 0,
    'origin_x' => $request->origin_x ?? 0,
    'origin_y' => $request->origin_y ?? 0,
    'scale' => $request->scale ?? 1,
        ]);

        return response()->json($floorplan, 201);
    }

    // Update a floorplan (title or file)
    public function update(Request $request, Project $project, FloorPlan $floorplan)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'file'  => 'sometimes|file|mimes:pdf|max:10240',
        ]);

        if ($request->has('title')) {
            $floorplan->title = $request->title;
        }

        if ($request->hasFile('file')) {
            // Delete old file
            if (Storage::disk('public')->exists($floorplan->file_path)) {
                Storage::disk('public')->delete($floorplan->file_path);
            }
            $file = $request->file('file');
            $floorplan->file_path = $file->store('floorplans', 'public');
            $floorplan->original_filename = $file->getClientOriginalName();
        }

        $floorplan->save();

        return response()->json($floorplan);
    }

    // Soft delete a floorplan (move to recycle bin)
    public function destroy(Project $project, FloorPlan $floorplan)
    {
        $user = auth()->user();
        
        // Verify the floorplan belongs to this project
        if ($floorplan->project_id !== $project->id) {
            return response()->json(['error' => 'Floorplan does not belong to this project'], 403);
        }
        
        // Check if user has permission to delete floorplans
        if (!$user->hasPermission('delete_project')) {
            return response()->json(['error' => 'Unauthorized: You do not have permission to delete floorplans'], 403);
        }
        
        $floorplan->delete(); // soft delete
        return response()->json(['message' => 'Floorplan moved to recycle bin']);
    }

    // List all soft-deleted floorplans (recycle bin)
    public function recycleBin(Project $project)
    {
        $deletedPlans = $project->floorPlans()->onlyTrashed()->get();
        return response()->json($deletedPlans);
    }
     // Restore a soft-deleted floorplan
    public function restore(Project $project, $id)
    {
        $floorplan = FloorPlan::withTrashed()->findOrFail($id);
        $floorplan->restore();

        return response()->json(['message' => 'Floorplan restored successfully']);
    }

    // Permanently delete a floorplan
    public function forceDelete(Project $project, $id)
    {
        $floorplan = FloorPlan::withTrashed()->findOrFail($id);

        if (Storage::disk('public')->exists($floorplan->file_path)) {
            Storage::disk('public')->delete($floorplan->file_path);
        }

        $floorplan->forceDelete();

        return response()->json(['message' => 'Floorplan permanently deleted']);
    }

    public function updateOrigin(Request $request, Project $project, FloorPlan $floorplan)
{
    $request->validate([
        'origin_x' => 'required|numeric',
        'origin_y' => 'required|numeric',
    ]);

    $floorplan->update([
        'origin_x' => $request->origin_x,
        'origin_y' => $request->origin_y,
    ]);

    return response()->json([
        'message' => 'Origin updated successfully',
        'data' => $floorplan
    ]);
}
public function updateRotation(Request $request, Project $project, FloorPlan $floorplan)
{
    $request->validate([
        'rotation_angle' => 'required|numeric|min:0|max:360',
    ]);

    $floorplan->update([
        'rotation_angle' => $request->rotation_angle,
    ]);

    return response()->json([
        'message' => 'Rotation updated successfully',
        'data' => $floorplan
    ]);
}
public function updateCalibration(Request $request, Project $project, FloorPlan $floorplan)
{
    $request->validate([
        'pixel_distance' => 'required|numeric|min:1',
        'real_distance'  => 'required|numeric|min:0.0001'
    ]);

    $scale = $request->real_distance / $request->pixel_distance;

    $floorplan->update([
        'scale' => $scale
    ]);

    return response()->json([
        'message' => 'Calibration completed',
        'scale' => $scale,
        'data' => $floorplan
    ]);
}
public function getSettings(Project $project, FloorPlan $floorplan)
{
    $user = auth()->user();
    if ($user && $user->role && in_array($user->role->name, ['subcontractor', 'basic', 'user'])) {
        return response()->json(['message' => 'You do not have permission to view floor plan settings. Only super_admin, admin, or manager may access this.'], 403);
    }

    return response()->json([
        'origin_x' => $floorplan->origin_x,
        'origin_y' => $floorplan->origin_y,
        'rotation_angle' => $floorplan->rotation_angle,
        'scale' => $floorplan->scale,
    ]);
} 


}
