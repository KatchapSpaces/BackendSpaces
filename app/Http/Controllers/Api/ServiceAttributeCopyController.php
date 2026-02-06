<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceAttributeCopyController extends Controller
{
    public function copy(Request $request)
    {
        $validated = $request->validate([
            'source_service_id' => 'required|exists:services,id',
            'target_service_id' => 'required|exists:services,id',
        ]);

        // Get attributes of source service
        $attributes = AttributeAccountable::where(
            'service_id',
            $validated['source_service_id']
        )->get();

        if ($attributes->isEmpty()) {
            return response()->json([
                'message' => 'Source service has no attributes'
            ], 404);
        }

        $copied = [];

        DB::transaction(function () use ($attributes, $validated, &$copied, $request) {

            foreach ($attributes as $attr) {

                $exists = ServiceAttributeCopy::where([
                    'source_service_id' => $validated['source_service_id'],
                    'target_service_id' => $validated['target_service_id'],
                    'attribute_id'      => $attr->attribute_id,
                ])->exists();

                if ($exists) continue;

                // 🔹 Copy accountable too
                AttributeAccountable::create([
                    'attribute_id' => $attr->attribute_id,
                    'space_id'     => $attr->space_id,
                    'service_id'   => $validated['target_service_id'],
                    'assigned_to'  => $attr->assigned_to,
                    'created_by'   => $request->user()?->id,
                ]);

                $copied[] = ServiceAttributeCopy::create([
                    'source_service_id' => $validated['source_service_id'],
                    'target_service_id' => $validated['target_service_id'],
                    'attribute_id'      => $attr->attribute_id,
                    'copied_by'         => $request->user()?->id,
                ]);
            }
        });

        return response()->json([
            'message' => 'Attributes copied successfully',
            'data' => $copied
        ], 201);
    }
}

