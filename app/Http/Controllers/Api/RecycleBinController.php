<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\Service;
use App\Models\Attribute;
use Illuminate\Http\Request;

class RecycleBinController extends Controller
{
    public function deleted()
    {
        return response()->json([
            'spaces' => Space::onlyTrashed()->get(),
            'services' => Service::onlyTrashed()->get(),
            'attributes' => Attribute::onlyTrashed()->get(),
        ]);
    }

    // --------------------
    // DELETE FUNCTIONS
    // --------------------

    public function deleteSpace($id)
    {
        $space = Space::findOrFail($id);

        // Soft delete services + attributes
        foreach ($space->services as $service) {
            $service->attributes()->delete();
            $service->delete();
        }

        $space->delete();

        return response()->json(['message' => 'Space moved to recycle bin']);
    }


    public function deleteService($id)
    {
        $service = Service::findOrFail($id);

        // Soft delete attributes
        $service->attributes()->delete();

        $service->delete();

        return response()->json(['message' => 'Service moved to recycle bin']);
    }


    public function deleteAttribute($id)
    {
        $attribute = Attribute::findOrFail($id);

        $attribute->delete();

        return response()->json(['message' => 'Attribute moved to recycle bin']);
    }



    // --------------------
    // RESTORE FUNCTIONS
    // --------------------

    public function restoreSpace($id)
    {
        $space = Space::onlyTrashed()->findOrFail($id);

        // Restore main space
        $space->restore();

        // Restore nested items
        Service::onlyTrashed()->where('space_id', $id)->restore();
        Attribute::onlyTrashed()
            ->whereIn('service_id', $space->services()->pluck('id'))
            ->restore();

        return response()->json(['message' => 'Space restored successfully']);
    }


    public function restoreService($id)
    {
        $service = Service::onlyTrashed()->findOrFail($id);

        $service->restore();

        Attribute::onlyTrashed()
            ->where('service_id', $id)
            ->restore();

        return response()->json(['message' => 'Service restored successfully']);
    }


    public function restoreAttribute($id)
    {
        Attribute::onlyTrashed()->findOrFail($id)->restore();

        return response()->json(['message' => 'Attribute restored successfully']);
    }



    // --------------------
    // PERMANENT DELETE
    // --------------------

    public function forceDeleteSpace($id)
    {
        $space = Space::onlyTrashed()->findOrFail($id);

        // Permanently delete child data
        Service::onlyTrashed()->where('space_id', $id)->forceDelete();
        Attribute::onlyTrashed()
            ->whereIn('service_id', $space->services()->pluck('id'))
            ->forceDelete();

        $space->forceDelete();

        return response()->json(['message' => 'Space permanently deleted']);
    }

    public function forceDeleteService($id)
    {
        $service = Service::onlyTrashed()->findOrFail($id);

        Attribute::onlyTrashed()->where('service_id', $id)->forceDelete();

        $service->forceDelete();

        return response()->json(['message' => 'Service permanently deleted']);
    }

    public function forceDeleteAttribute($id)
    {
        Attribute::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['message' => 'Attribute permanently deleted']);
    }
}
