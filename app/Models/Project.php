<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'revision',
        'site_number',
        'timezone',
        'address',
        'location',
        'measurement',
        'image_path',
        'created_by',
        'assigned_admin_id',
        'assigned_manager_id',
    ];
    public function floorPlans() {
        return $this->hasMany(FloorPlan::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function siteTeam()
{
    return $this->hasMany(SiteTeam::class);
}

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function assignedManager()
    {
        return $this->belongsTo(User::class, 'assigned_manager_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

}
