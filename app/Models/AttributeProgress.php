<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeProgress extends Model
{
    use HasFactory;

    protected $table = 'attribute_progress';

    protected $fillable = [
        'space_id',
        'service_id',
        'attribute_id',
        'description',
        'start_date',
        'end_date',
        'progress',
        'assigned_to',
        'created_by',
    ];

    /**
     * The space this progress belongs to
     */
    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id', 'number');
    }

    /**
     * The service this progress belongs to
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The attribute this progress belongs to
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * The user assigned to this progress
     */
 
  public function assignedMember()
    {
        return $this->belongsTo(SiteTeam::class, 'assigned_to');
    }
    /**
     * The user who created this progress
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Optional: Accessor for progress percentage
     */
    public function getProgressPercentAttribute()
    {
        return $this->progress ? $this->progress . '%' : '0%';
    }
    public function progresses()
{
    return $this->hasMany(ProgramProgress::class, 'attribute_program_id');
}

public function latestProgress()
{
    return $this->hasOne(ProgramProgress::class, 'attribute_program_id')
                ->latestOfMany();
}

}
