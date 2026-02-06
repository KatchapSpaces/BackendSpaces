<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\FloorPlan;
use App\Models\AttributeProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttributeProgressController extends Controller
{
    /**
     * Fetch all progress records, optionally filtered
     */
    public function index(Request $request)
    {
        $q = AttributeProgress::query();

        if ($request->has('attribute_id')) $q->where('attribute_id', $request->query('attribute_id'));
        if ($request->has('space_id')) $q->where('space_id', $request->query('space_id'));
        if ($request->has('service_id')) $q->where('service_id', $request->query('service_id'));

        $progressRecords = $q->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $progressRecords], 200);
    }

    /**
     * Store a new progress record
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|integer',
            'space_number' => 'required|string|exists:spaces,number',
            'service_id' => 'nullable|integer',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'assigned_to' => 'nullable|integer|exists:site_teams,id',
        ]);

        $space = Space::where('number', $validated['space_number'])->firstOrFail();

        $progress = AttributeProgress::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id' => $space->number,
            'service_id' => $validated['service_id'] ?? null,
            'description' => $validated['description'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'progress' => $validated['progress'] ?? 0,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user() ? $request->user()->id : null,
        ]);

        return response()->json(['data' => $progress], 201);
    }

    /**
     * Show a single progress record
     */
    public function show($id)
    {
        $progress = AttributeProgress::findOrFail($id);
        return response()->json(['data' => $progress], 200);
    }

    /**
     * Update a progress record
     */
    public function update(Request $request, $id)
    {
        $progress = AttributeProgress::findOrFail($id);

        $validated = $request->validate([
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'assigned_to' => 'nullable|integer|exists:site_teams,id',
        ]);

        $progress->update($validated);

        return response()->json(['data' => $progress], 200);
    }

    /**
     * Delete a progress record
     */
    public function destroy($id)
    {
        $progress = AttributeProgress::findOrFail($id);
        $progress->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
