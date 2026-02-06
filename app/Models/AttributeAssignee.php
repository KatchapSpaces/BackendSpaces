<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeAssignee extends Model
{
    use HasFactory;

    protected $table = 'attribute_assignee';

    protected $fillable = [
        'space_id',
        'service_id',
        'attribute_id',
        'assigned_to',
        'created_by',
    ];

    /**
     * The space this assignee belongs to
     */
    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id', 'number');
    }

    /**
     * The service this assignee belongs to
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The attribute this assignee belongs to
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * The user assigned
     */
    public function assignedMember()
    {
        return $this->belongsTo(SiteTeam::class, 'assigned_to');
    }

    /**
     * The user who created this record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
