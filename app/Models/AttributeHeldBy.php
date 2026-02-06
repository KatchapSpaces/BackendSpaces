<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeHeldBy extends Model
{
    use HasFactory;

    protected $table = 'attribute_held_by';

    protected $fillable = [
        'space_id',
        'service_id',
        'attribute_id',
        'created_by',
        'assigned_to',
        'motive',
        'status',
    ];

    /**
     * Creator of Held By
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Assigned user
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Responses for this Held By
     */
    public function responses()
    {
        return $this->hasMany(AttributeHeldByResponse::class, 'held_by_id');
    }
}
