<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\FloorPlan;
use App\Models\AttributeObservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttributeObservationController extends Controller
{
    public function index(Request $request)
{
    $q = AttributeObservation::with(['creator', 'assignedMember']); // 👈 FIX

    if ($request->has('attribute_id')) {
        $q->where('attribute_id', $request->query('attribute_id'));
    }

    if ($request->has('space_id')) {
        $q->where('space_id', $request->query('space_id'));
    }

    if ($request->has('service_id')) {
        $q->where('service_id', $request->query('service_id'));
    }

    $obs = $q->orderBy('created_at', 'desc')->get();

    return response()->json([
        'data' => $obs->map(function ($o) {
            return array_merge(
                $o->toArray(),
                ['attachment_url' => $o->attachment_url]
            );
        })
    ], 200);
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|integer',
            'space_number' => 'required|string|exists:spaces,number',
            'service_id' => 'nullable|integer',
            'description' => 'required|string',
            'deadline' => 'nullable|date',
            'attachment' => 'nullable|file|image|max:5120',
            'status' => 'nullable|string',
             'company_name' => 'nullable|string|max:255',
    'assigned_to' => 'nullable|integer|exists:site_teams,id',
       
        ]);

           // Find the space by number
    $space = Space::where('number', $validated['space_number'])->firstOrFail();

        // Optional: ensure assigned_to belongs to the same project (if you have project_id)
  // Optional: ensure assigned_to belongs to the same project
    if (!empty($validated['assigned_to'])) {
        $floorplan = FloorPlan::find($space->floorplan_id);
        if (!$floorplan) {
            return response()->json(['error' => 'FloorPlan not found.'], 404);
        }

        $siteTeam = \App\Models\SiteTeam::where('id', $validated['assigned_to'])
            ->where('project_id', $floorplan->project_id)
            ->first();

        if (!$siteTeam) {
            return response()->json(['error' => 'Assigned member does not belong to this project.'], 422);
        }
    }



        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = 'storage/' . $request->file('attachment')->store('attribute_observations', 'public');
        }

        $obs = AttributeObservation::create([
            'attribute_id' => $validated['attribute_id'],
                'space_id' => $space->number,
            'service_id' => $validated['service_id'] ?? null,
            'description' => $validated['description'],
            'deadline' => $validated['deadline'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => $validated['status'] ?? 'open',
            'company_name' => $validated['company_name'] ?? null,
    'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user() ? $request->user()->id : null,
        ]);

        return response()->json(['data' => $obs, 'attachment_url' => $obs->attachment_url], 201);
    }

    public function show($id)
    {
        $obs = AttributeObservation::findOrFail($id);
        return response()->json(['data' => array_merge($obs->toArray(), ['attachment_url' => $obs->attachment_url])], 200);
    }

  public function update(Request $request, $projectId, $floorplanId, $id)
{
    $obs = AttributeObservation::findOrFail($id);

    $validated = $request->validate([
        'description' => 'nullable|string',
        'deadline' => 'nullable|date',
        'status' => 'nullable|string',
        'company_name' => 'nullable|string|max:255',
        'assigned_to' => 'nullable|integer|exists:site_teams,id',
        'attachment' => 'nullable|file|image|max:5120',
    ]);

    // If updating with assigned_to, verify same project
   if (!empty($validated['assigned_to']) && $obs->space_id) {
    // Find space using number instead of ID
    $space = Space::where('number', $obs->space_id)->first();

    if (!$space) {
        return response()->json(['error' => 'Space not found'], 404);
    }

    $floorplan = FloorPlan::find($space->floorplan_id);

    if (!$floorplan) {
        return response()->json(['error' => 'FloorPlan not found'], 404);
    }

    $siteTeam = \App\Models\SiteTeam::where('id', $validated['assigned_to'])
        ->where('project_id', $floorplan->project_id)
        ->first();

    if (!$siteTeam) {
        return response()->json(['error' => 'Assigned member does not belong to this project.'], 422);
    }
}
    // Handle attachment update
    if ($request->hasFile('attachment')) {

        if ($obs->attachment_path) {
            $storagePath = str_replace('storage/', '', $obs->attachment_path);
            if (Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }
        }

        $validated['attachment_path'] =
            'storage/' . $request->file('attachment')->store('attribute_observations', 'public');
    }

    $obs->update($validated);

    return response()->json([
        'data' => $obs,
        'attachment_url' => $obs->attachment_url
    ], 200);
}


public function destroy($projectId, $floorplanId, $id)
{
    $observation = AttributeObservation::find($id);

    if (!$observation) {
        return response()->json([
            'message' => 'Observation not found'
        ], 404);
    }

    // Delete attachment if exists
    if ($observation->attachment_path) {
        $storagePath = str_replace('storage/', '', $observation->attachment_path);
        if (Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }

    $observation->delete();

    return response()->json([
        'message' => 'Deleted successfully'
    ]);
}



}
