<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeAccountable;
use App\Models\Space;
use Illuminate\Http\Request;

class AttributeAccountableController extends Controller
{
    /**
     * Fetch all accountable records, optionally filtered
     */
    public function index(Request $request)
    {
        $q = AttributeAccountable::query();

        if ($request->has('attribute_id')) $q->where('attribute_id', $request->query('attribute_id'));
        if ($request->has('space_id')) $q->where('space_id', $request->query('space_id'));
        if ($request->has('service_id')) $q->where('service_id', $request->query('service_id'));

        $records = $q->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $records], 200);
    }

    /**
     * Store new accountable record(s)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|integer|exists:attributes,id',
            'space_number' => 'required|string|exists:spaces,number',
            'service_id'   => 'nullable|integer|exists:services,id',
            'assigned_to'  => 'required',
            'assigned_to.*'=> 'integer|exists:users,id',
        ]);

        $space = Space::where('number', $validated['space_number'])->firstOrFail();

        $assignedUsers = is_array($validated['assigned_to'])
            ? $validated['assigned_to']
            : [$validated['assigned_to']];

        $created = [];

        foreach ($assignedUsers as $userId) {
            // Prevent duplicate assignment
            $exists = AttributeAccountable::where([
                'attribute_id' => $validated['attribute_id'],
                'space_id'     => $space->number,
                'service_id'   => $validated['service_id'] ?? null,
                'assigned_to'  => $userId,
            ])->exists();

            if ($exists) continue;

            $created[] = AttributeAccountable::create([
                'attribute_id' => $validated['attribute_id'],
                'space_id'     => $space->number,
                'service_id'   => $validated['service_id'] ?? null,
                'assigned_to'  => $userId,
                'created_by'   => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => 'Accountables added successfully',
            'data'    => $created,
        ], 201);
    }

    /**
     * Show a single accountable record
     */
    public function show($id)
    {
        $record = AttributeAccountable::findOrFail($id);
        return response()->json(['data' => $record], 200);
    }

    /**
     * Update accountable record(s) for a space + attribute + service
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

        // Remove existing records
        AttributeAccountable::where([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
        ])->delete();

        $created = [];
        foreach ($validated['assigned_to'] as $userId) {
            $created[] = AttributeAccountable::create([
                'attribute_id' => $validated['attribute_id'],
                'space_id'     => $space->number,
                'service_id'   => $validated['service_id'] ?? null,
                'assigned_to'  => $userId,
                'created_by'   => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => 'Accountables updated successfully',
            'data'    => $created,
        ], 200);
    }

    /**
     * Delete accountable record(s) for a space + attribute + service
     */
    public function destroy(Request $request, $project, $floorplan)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|integer',
            'space_number' => 'required|string',
            'service_id'   => 'nullable|integer',
        ]);

        $space = Space::where('number', $validated['space_number'])->firstOrFail();

        AttributeAccountable::where([
            'attribute_id' => $validated['attribute_id'],
            'space_id'     => $space->number,
            'service_id'   => $validated['service_id'] ?? null,
        ])->delete();

        return response()->json(['message' => 'Accountables removed']);
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
    AttributeAccountable::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    $created = [];
    foreach ($validated['assigned_to'] as $userId) {
        $created[] = AttributeAccountable::create([
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

    AttributeAccountable::where([
        'attribute_id' => $validated['attribute_id'],
        'space_id'     => $space->number,
        'service_id'   => $validated['service_id'] ?? null,
    ])->delete();

    return response()->json(['message' => 'Assignees removed']);
}
}
