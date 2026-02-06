<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramProgressController extends Controller
{
    /**
     * Store a new progress update
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_program_id' => 'required|exists:attribute_program,id',
            'progress' => 'required|integer|min:0|max:100',
        ]);

        $progress = ProgramProgress::create([
            'attribute_program_id' => $validated['attribute_program_id'],
            'progress' => $validated['progress'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Progress updated successfully',
            'data' => $progress->load('updatedBy'),
        ], 201);
    }

    /**
     * Get latest progress for a program
     */
   public function latest($programId)
{
    $progress = ProgramProgress::where('attribute_program_id', $programId)
        ->latest()
           ->with('updatedBy')
        ->first();

    return response()->json($progress);
}

/**
 * Get all progress updates for a program
 */
public function all($programId)
{
    $progresses = ProgramProgress::where('attribute_program_id', $programId)
        ->with('updatedBy')   // eager load the user who updated
        ->orderBy('created_at', 'asc') // optional: oldest first
        ->get();

    return response()->json([
        'data' => $progresses
    ], 200);
}

}
