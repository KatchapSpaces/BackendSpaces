<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'floorplan_id',
        'name',
        'number',
        'x',
        'y',
        'assigned_to',
        'assigned_by',
    ];

    public function floorplan()
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    // NEW RELATIONSHIPS
    public function assignedTo()
    {
        return $this->belongsTo(SiteTeam::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
