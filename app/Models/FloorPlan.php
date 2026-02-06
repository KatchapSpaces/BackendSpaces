<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FloorPlan extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'file_path',
        'original_filename',
        'origin_x',
    'origin_y',
    'rotation_angle',
    'scale',
    ];

    public function project() {
        return $this->belongsTo(Project::class);
    }
     // app/Models/FloorPlan.php
public function spaces()
{
    return $this->hasMany(Space::class, 'floorplan_id'); // use actual DB column
}

}
