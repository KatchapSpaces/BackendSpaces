<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FloorPlan;
use App\Models\Space;
use App\Models\Service;
use App\Models\Attribute;

class AnnotationController extends Controller
{
    /** -----------------------------------------------------------------
     *                     SPACES
     * -----------------------------------------------------------------*/

  public function createSpace(Request $request, $projectId, $floorplanId)
{
    $floorplan = FloorPlan::findOrFail($floorplanId);

    try {
        $validated = $request->validate([
            'name' => 'required|string',
            'number' => 'nullable|string',
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'assigned_to' => 'nullable|exists:site_teams,id', // optional dropdown selection
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::warning('createSpace validation failed', ['payload' => $request->all(), 'errors' => $e->errors()]);
        throw $e;
    }

    // Check if space number is already taken in this floor plan
    if (!empty($validated['number'])) {
        $existingSpace = Space::where('floorplan_id', $floorplanId)
            ->where('number', $validated['number'])
            ->first();
        
        if ($existingSpace) {
            return response()->json([
                'status' => false,
                'message' => 'This space number is already taken for this floor plan'
            ], 422);
        }
    }

    // Set assigned_by only if an assignee was provided
    if (!empty($validated['assigned_to'])) {
        $validated['assigned_by'] = auth()->id();
    }

    $space = $floorplan->spaces()->create($validated);

    return response()->json([
        'message' => 'Space created successfully',
        'data' => $space
    ]);
}

   public function updateSpace(Request $request, $projectId, $floorplanId, $spaceId)
{
    $space = Space::findOrFail($spaceId);

    try {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'number' => 'nullable|string',
            'x' => 'nullable|numeric|min:0|max:1',
            'y' => 'nullable|numeric|min:0|max:1',
            'assigned_to' => 'nullable|exists:site_teams,id', // optional update
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::warning('updateSpace validation failed', ['payload' => $request->all(), 'errors' => $e->errors()]);
        throw $e;
    }

    // Check if space number is already taken in this floor plan (excluding current space)
    if (!empty($validated['number'])) {
        $existingSpace = Space::where('floorplan_id', $space->floorplan_id)
            ->where('number', $validated['number'])
            ->where('id', '!=', $spaceId)
            ->first();
        
        if ($existingSpace) {
            return response()->json([
                'status' => false,
                'message' => 'This space number is already taken for this floor plan'
            ], 422);
        }
    }

    // Optionally update assigned_by if you want to track who updated
    $validated['assigned_by'] = auth()->id();

    $space->update($validated);

    return response()->json([
        'message' => 'Space updated successfully',
        'data' => $space
    ]);
}


    public function deleteSpace($projectId, $floorplanId, $spaceId)
    {
        $space = Space::findOrFail($spaceId);
        $space->delete();

        return response()->json(['message' => 'Space deleted successfully']);
    }

    /** -----------------------------------------------------------------
     *                     SERVICES
     * -----------------------------------------------------------------*/

    public function createService(Request $request, $projectId, $floorplanId, $spaceId)
    {
        $space = Space::findOrFail($spaceId);

        $validated = $request->validate([
            'name' => 'required|string',
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'icon' => 'nullable|string'
        ]);

        $service = $space->services()->create($validated);

        return response()->json([
            'message' => 'Service created successfully',
            'data' => $service
        ]);
    }

    public function updateService(Request $request, $projectId, $floorplanId, $serviceId)
    {
        $service = Service::findOrFail($serviceId);

        $validated = $request->validate([
            'name' => 'nullable|string',
            'x' => 'nullable|numeric|min:0|max:1',
            'y' => 'nullable|numeric|min:0|max:1',
            'icon' => 'nullable|string'
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully',
            'data' => $service
        ]);
    }

    public function deleteService($projectId, $floorplanId, $serviceId)
    {
        $service = Service::findOrFail($serviceId);
        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }

    /** -----------------------------------------------------------------
     *                      ATTRIBUTES
     * -----------------------------------------------------------------*/

    public function createAttribute(Request $request, $projectId, $floorplanId, $serviceId)
    {
        $service = Service::findOrFail($serviceId);

        $validated = $request->validate([
            'name' => 'required|string',
            'value' => 'nullable|string',
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'icon' => 'nullable|string'
        ]);

        $attribute = $service->attributes()->create($validated);

        return response()->json([
            'message' => 'Attribute created successfully',
            'data' => $attribute
        ]);
    }

    public function updateAttribute(Request $request, $projectId, $floorplanId, $attributeId)
    {
        $attribute = Attribute::findOrFail($attributeId);

        $validated = $request->validate([
            'name' => 'nullable|string',
            'value' => 'nullable|string',
            'x' => 'nullable|numeric|min:0|max:1',
            'y' => 'nullable|numeric|min:0|max:1',
            'icon' => 'nullable|string'
        ]);

        $attribute->update($validated);

        return response()->json([
            'message' => 'Attribute updated successfully',
            'data' => $attribute
        ]);
    }

    public function deleteAttribute($projectId, $floorplanId, $attributeId)
    {
        $attribute = Attribute::findOrFail($attributeId);
        $attribute->delete();

        return response()->json(['message' => 'Attribute deleted successfully']);
    }

    /** -----------------------------------------------------------------
     *                    LOAD ALL
     * -----------------------------------------------------------------*/

    public function load($projectId, $floorplanId)
    {
        $floorplan = FloorPlan::findOrFail($floorplanId);

        $spaces = $floorplan->spaces()
            ->with(['services.attributes', 'assignedTo.user', 'assignedBy'])
            ->get();

        return response()->json([
            'message' => 'Loaded successfully',
            'data' => $spaces
        ]);
    }
}
