<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeHeldBy;
use App\Models\AttributeHeldByResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttributeHeldByController extends Controller
{
    // 1️⃣ List Held By for a space/service/attribute
    public function index(Request $request)
    {
        $q = AttributeHeldBy::query();

        if ($request->has('space_id')) $q->where('space_id', $request->query('space_id'));
        if ($request->has('service_id')) $q->where('service_id', $request->query('service_id'));
        if ($request->has('attribute_id')) $q->where('attribute_id', $request->query('attribute_id'));

        $heldBy = $q->with('responses')->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $heldBy], 200);
    }

    // 2️⃣ Create new Held By
    public function store(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|string|exists:spaces,number',
            'service_id' => 'required|integer|exists:services,id',
            'attribute_id' => 'required|integer|exists:attributes,id',
            'assigned_to' => 'nullable|integer|exists:site_teams,id',
            'motive' => 'required|string',
        ]);

        $heldBy = AttributeHeldBy::create([
            'space_id' => $validated['space_id'],
            'service_id' => $validated['service_id'],
            'attribute_id' => $validated['attribute_id'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'motive' => $validated['motive'],
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $heldBy], 201);
    }

    // 3️⃣ Assignee responds
    public function respond(Request $request, $id)
    {
        
        $heldBy = AttributeHeldBy::findOrFail($id);

        // Only assignee can respond
        // if ($heldBy->assigned_to != $request->user()->id) {
        //     return response()->json(['error' => 'Not authorized to respond'], 403);
        // }

  

        $validated = $request->validate([
            'response_text' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = 'storage/' . $request->file('attachment')->store('held_by_responses', 'public');
        }

        $response = AttributeHeldByResponse::create([
            'held_by_id' => $heldBy->id,
            'response_text' => $validated['response_text'],
            'attachment' => $attachmentPath,
            'responded_by' => $request->user()->id,
        ]);

        // Update Held By status
        $heldBy->status = 'responded';
        $heldBy->save();

        return response()->json(['data' => $response], 201);
    }

    // 4️⃣ Creator accepts response
    public function accept(Request $request, $id)
    {
        $heldBy = AttributeHeldBy::findOrFail($id);

        if ($heldBy->created_by != $request->user()->id) {
            return response()->json(['error' => 'Not authorized to accept'], 403);
        }

        $heldBy->status = 'accepted';
        $heldBy->save();

        // Optionally, auto-close after accept
        $heldBy->status = 'closed';
        $heldBy->save();

        return response()->json(['data' => $heldBy], 200);
    }

    // 5️⃣ Creator rejects response
    public function reject(Request $request, $id)
    {
        $heldBy = AttributeHeldBy::findOrFail($id);

        if ($heldBy->created_by != $request->user()->id) {
            return response()->json(['error' => 'Not authorized to reject'], 403);
        }

        $heldBy->status = 'rejected';
        $heldBy->save();

        return response()->json(['data' => $heldBy], 200);
    }

    // 6️⃣ Get Held By history (responses)
    public function history($id)
    {
        $heldBy = AttributeHeldBy::with('responses')->findOrFail($id);

        return response()->json(['data' => $heldBy], 200);
    }
}
