<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Space;
use App\Models\Service;
use App\Models\Attribute;

class AttributeImage extends Model
{
    protected $table = 'attribute_images';

    protected $fillable = [
        'space_id', 'service_id', 'attribute_id',
        'image_path', 'title', 'description',
        'latitude', 'longitude',
        'uploaded_by', 'uploaded_at'
    ];

    protected $dates = ['uploaded_at', 'created_at', 'updated_at'];

    // Relationships (adjust namespace for your User, Space, Service, Attribute models)
      public function user() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function space()
    {
        return $this->belongsTo(Space::class, 'space_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    // helper: full url
    public function getUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . ltrim(str_replace('storage/', '', $this->image_path), '/')) : null;
    }
}
