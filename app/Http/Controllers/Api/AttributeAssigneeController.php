<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeAssignee;
use App\Models\Space;
use Illuminate\Http\Request;

class AttributeAssigneeController extends Controller
{
    /**
     * Fetch all assignee records, optionally filtered
     */
    public function index(Request $request)
    {
        $q = AttributeAssignee::query();

        if ($request->has('attribute_id')) $q->where('attribute_id', $request->query('attribute_id'));
        if ($request->has('space_id')) $q->where('space_id', $request->query('space_id'));
        if ($request->has('service_id')) $q->where('service_id', $request->query('service_id'));

        $assignees = $q->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $assignees], 200);
    }

    /**
     * Store a new assignee entry
     */
   public function store(Request $request)
{
    $validated = $request->validate([
        'attribute_id' => 'required|integer|exists:attributes,id',
        'space_number' => 'required|string|exists:spaces,number',
        'service_id' => 'nullable|integer|exists:services,id',
        'assigned_to' => 'required',
        'assigned_to.*' => 'integer|exists:users,id',
    ]);

    $space = Space::where('number', $validated['space_number'])->firstOrFail();

    // Normalize assigned_to into array
    $assignedUsers = is_array($validated['assigned_to'])
        ? $validated['assigned_to']
        : [$validated['assigned_to']];

    $created = [];

    foreach ($assignedUsers as $userId) {

        // 🔒 Prevent duplicate assignment
        $exists = AttributeAssignee::where([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
            'assigned_to'  => $userId,
        ])->exists();

        if ($exists) {
            continue;
        }

        $created[] = AttributeAssignee::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
            'assigned_to'  => $userId,
            'created_by'   => $request->user()?->id,
        ]);
    }

    return response()->json([
        'message' => 'Assignees added successfully',
        'data'    => $created,
    ], 201);
}


    /**
     * Show a single assignee record
     */
    public function show($id)
    {
        $assignee = AttributeAssignee::findOrFail($id);
        return response()->json(['data' => $assignee], 200);
    }

    /**
     * Update an assignee record
     */
   public function update(Request $request, $project, $floorplan)
{
    $validated = $request->validate([
        'attribute_id' => 'required|integer|exists:attributes,id',
        'space_number' => 'required|string|exists:spaces,number',
        'service_id'   => 'nullable|integer|exists:services,id',
        'assigned_to'  => 'required|array',
        'assigned_to.*'=> 'integer|exists:users,id',
    ]);

    $space = Space::where('number', $validated['space_number'])->firstOrFail();

    // 🔥 Remove existing assignments
    AttributeAssignee::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    $created = [];

    foreach ($validated['assigned_to'] as $userId) {
        $created[] = AttributeAssignee::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
            'assigned_to'  => $userId,
            'created_by'   => $request->user()?->id,
        ]);
    }

    return response()->json([
        'message' => 'Assignees updated successfully',
        'data'    => $created,
    ], 200);
}

 public function destroy(Request $request, $project, $floorplan)
{
    $validated = $request->validate([
        'attribute_id' => 'required|integer',
        'space_number' => 'required|string',
        'service_id'   => 'nullable|integer',
    ]);

    $space = Space::where('number', $validated['space_number'])->firstOrFail();

    AttributeAssignee::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    return response()->json(['message' => 'Assignees removed']);
}
public function bulkUpdate(Request $request, $project, $floorplan)
{
    $validated = $request->validate([
        'attribute_id' => 'required|integer|exists:attributes,id',
        'space_number' => 'required|string|exists:spaces,number',
        'service_id'   => 'nullable|integer|exists:services,id',
        'assigned_to'  => 'required|array',
        'assigned_to.*'=> 'integer|exists:users,id',
    ]);

    $space = Space::where('number', $validated['space_number'])->firstOrFail();

    // Delete existing assignments for this attribute + space + service
    AttributeAssignee::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    $created = [];
    foreach ($validated['assigned_to'] as $userId) {
        $created[] = AttributeAssignee::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
            'assigned_to'  => $userId,
            'created_by'   => $request->user()?->id,
        ]);
    }

    return response()->json([
        'message' => 'Assignees updated successfully',
        'data'    => $created,
    ], 200);
}

public function bulkDelete(Request $request, $project, $floorplan)
{
    $validated = $request->validate([
        'attribute_id' => 'required|integer',
        'space_number' => 'required|string',
        'service_id'   => 'nullable|integer',
    ]);

    $space = Space::where('number', $validated['space_number'])->firstOrFail();

    AttributeAssignee::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    return response()->json(['message' => 'Assignees removed']);
}


}
