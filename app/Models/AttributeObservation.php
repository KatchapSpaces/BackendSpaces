<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeObservation extends Model
{
    protected $table = 'attribute_observations';

    protected $fillable = [
        'attribute_id',
        'space_id',
        'service_id',
        'description',
        'deadline',
        'attachment_path',
        'status',
        'created_by',
          'company_name', // NEW
        'assigned_to',  // NEW
    ];

     public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedMember()
    {
        return $this->belongsTo(SiteTeam::class, 'assigned_to');
    }
    // Optional: accessors
public function getCreatorNameAttribute()
{
    return $this->creator ? $this->creator->name : null;
}

public function getAssigneeNameAttribute()
{
    return $this->assignee ? $this->assignee->name : null;
}

    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id', 'number');
    }


    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    // Accessor for attachment URL
    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_path
            ? asset('storage/' . ltrim(str_replace('storage/', '', $this->attachment_path), '/'))
            : null;
    }
}
