<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteTeam extends Model
{
    use HasFactory;

    protected $fillable = [
       'user_id',
        'project_id',
        'role',
        'created_by',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user() // the actual login user
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
