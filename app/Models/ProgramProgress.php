<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramProgress extends Model
{
    use HasFactory;

    protected $table = 'program_progress';

    protected $fillable = [
        'attribute_program_id',
        'progress',
        'updated_by',
    ];

    /**
     * Each progress belongs to a program
     */
    public function program()
    {
        return $this->belongsTo(AttributeProgram::class, 'attribute_program_id');
    }

    /**
     * User who updated progress
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
