<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttributeImageController extends Controller
{
    // list images, filterable by attribute_id, space_id, service_id
    public function index(Request $request)
    {
        $query = AttributeImage::query();

        if ($request->has('attribute_id')) {
            $query->where('attribute_id', $request->query('attribute_id'));
        }
        if ($request->has('space_id')) {
            $query->where('space_id', $request->query('space_id'));
        }
        if ($request->has('service_id')) {
            $query->where('service_id', $request->query('service_id'));
        }

        $images = $query->with('user')   // <-- IMPORTANT
    ->orderBy('created_at', 'desc')
    ->get()
    ->map(function ($img) {
        return array_merge($img->toArray(), [
            'url' => $img->url,
        ]);
    });


        return response()->json(['data' => $images], 200);
    }

  // store (bulk file upload)
public function store(Request $request)
{
    // Validate single or multiple files
    $validated = $request->validate([
        'attribute_id' => 'required|exists:attributes,id',
        'space_id' => 'required|exists:spaces,number',
        'service_id' => 'required|exists:services,id',

        // Accept either a single image OR multiple images
        'image' => 'sometimes|file|mimetypes:image/jpeg,image/png,image/webp|max:15360',
        'images.*' => 'sometimes|file|mimetypes:image/jpeg,image/png,image/webp|max:15360', // multiple files

        'title' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'latitude' => 'nullable|string|max:50',
        'longitude' => 'nullable|string|max:50',
    ]);

    $uploadedImages = [];

    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('attribute_images', 'public');
        $uploadedImages[] = AttributeImage::create([
            'attribute_id' => $validated['attribute_id'],
            'space_id' => $validated['space_id'],
            'service_id' => $validated['service_id'],
            'image_path' => $path,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'uploaded_by' => $request->user()?->id,
            'uploaded_at' => now(),
        ]);
    }

    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $file) {
            $uploadedImages[] = AttributeImage::create([
                'attribute_id' => $validated['attribute_id'],
                'space_id' => $validated['space_id'],
                'service_id' => $validated['service_id'],
                'image_path' => $file->store('attribute_images', 'public'),
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'uploaded_by' => $request->user()?->id,
                'uploaded_at' => now(),
            ]);
        }
    }

    return response()->json([
        'data' => $uploadedImages,
    ], 201);
}



    // update (metadata only)
    public function update(Request $request, $id)
    {
        $img = AttributeImage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
       
        ]);

        $img->update($validated);

        return response()->json(['data' => $img, 'url' => $img->url], 200);
    }

  
public function destroy($projectId, $floorplanId, $id)
{
    // Find the image by ID only
    $image = AttributeImage::find($id);

    if (!$image) {
        return response()->json([
            'message' => 'Image not found'
        ], 404);
    }

    // Delete the file from storage if it exists
    if ($image->image_path && \Storage::disk('public')->exists($image->image_path)) {
        \Storage::disk('public')->delete($image->image_path);
    }

    // Delete the DB record
    $image->delete();

    return response()->json([
        'message' => 'Image deleted successfully'
    ]);
}
}
