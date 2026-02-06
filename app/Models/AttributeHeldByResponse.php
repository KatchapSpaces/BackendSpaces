<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeHeldByResponse extends Model
{
    use HasFactory;

    protected $table = 'attribute_held_by_responses';

    protected $fillable = [
        'held_by_id',
        'response_text',
        'attachment',
        'responded_by',
    ];

    /**
     * The Held By this response belongs to
     */
    public function heldBy()
    {
        return $this->belongsTo(AttributeHeldBy::class, 'held_by_id');
    }

    /**
     * User who responded
     */
    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Optional accessor to get full attachment URL
     */
    public function getAttachmentUrlAttribute()
    {
        return $this->attachment ? asset($this->attachment) : null;
    }
}
