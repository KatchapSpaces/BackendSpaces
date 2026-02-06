<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['space_id', 'name', 'x', 'y', 'icon', 'assigned_subcontractor_id'];

    public function space() {
        return $this->belongsTo(Space::class);
    }

    public function attributes() {
        return $this->hasMany(Attribute::class);
    }

    public function assignedSubcontractor()
    {
        return $this->belongsTo(User::class, 'assigned_subcontractor_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
