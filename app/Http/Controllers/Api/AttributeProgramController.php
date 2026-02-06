<?php 

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeProgram;
use App\Models\Space;
use Illuminate\Http\Request;

class AttributeProgramController extends Controller
{
    /**
     * Fetch all program records, optionally filtered
     */
    public function index(Request $request)
    {
        $q = AttributeProgram::query();

        if ($request->has('attribute_id')) $q->where('attribute_id', $request->query('attribute_id'));
        if ($request->has('space_id')) $q->where('space_id', $request->query('space_id'));
        if ($request->has('service_id')) $q->where('service_id', $request->query('service_id'));

        $programs = $q->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $programs], 200);
    }

    /**
     * Store a new program entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|integer|exists:attributes,id',
            'space_number' => 'required|string|exists:spaces,number',
            'service_id' => 'nullable|integer|exists:services,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_to' => 'nullable|integer|exists:site_teams,id',
        ]);

        $space = Space::where('number', $validated['space_number'])->firstOrFail();

        $program = AttributeProgram::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id' => $space->number,
            'service_id' => $validated['service_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user() ? $request->user()->id : null,
        ]);

        return response()->json(['data' => $program], 201);
    }

    /**
     * Show a single program record
     */
    public function show($id)
    {
        $program = AttributeProgram::findOrFail($id);
        return response()->json(['data' => $program], 200);
    }

    /**
     * Update a program record
     */
    public function update(Request $request, $projectId, $floorplanId, $programId)
    {
        \Log::info('Program ID from route: ' . $programId);

        // Explicitly fetch the program by ID
        $program = AttributeProgram::findOrFail($programId);
        \Log::info('Program fetched: ' . $program->id);
$input = $request->all();
    $validated = validator($input, [
    'space_id' => 'nullable|string|exists:spaces,number',
    'service_id' => 'nullable|integer|exists:services,id',
    'attribute_id' => 'nullable|integer|exists:attributes,id',
    'start_date' => 'nullable|date',
    'end_date' => 'nullable|date|after_or_equal:start_date',
    'assigned_to' => 'nullable|integer|exists:site_teams,id',
])->validate();

foreach ($validated as $key => $value) {
    if (!is_null($value)) $program->$key = $value;
}
        $program->save();

        return response()->json(['data' => $program], 200);
    }

    /**
     * Delete a program entry
     */
   
    /**
     * Delete a program entry
     */
    public function destroy($projectId, $floorplanId, $id)
    {
        $program = AttributeProgram::findOrFail($id);
        $program->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

}
