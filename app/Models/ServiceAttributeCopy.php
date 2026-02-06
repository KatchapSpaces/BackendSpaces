<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAttributeCopy extends Model
{
    protected $fillable = [
        'source_service_id',
        'target_service_id',
        'attribute_id',
        'copied_by',
    ];

    public function sourceService()
    {
        return $this->belongsTo(Service::class, 'source_service_id');
    }

    public function targetService()
    {
        return $this->belongsTo(Service::class, 'target_service_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
