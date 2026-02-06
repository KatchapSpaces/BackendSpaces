<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'website',
        'logo',
        'status',
        'settings',
        'created_by_user_id',
        'activated_at',
        'suspended_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * Get the user who created this company
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get all users belonging to this company
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all projects created by users in this company
     */
    public function projects(): HasMany
    {
        return $this->hasManyThrough(Project::class, User::class, 'company_id', 'creator_id');
    }

    /**
     * Check if company is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if company is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Activate the company and all its users
     */
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
            'suspended_at' => null,
        ]);

        // Activate all users in this company except Super Admins
        $this->users()->whereHas('role', function($q) {
            $q->where('name', '!=', 'super_admin');
        })->update(['status' => 'active']);
    }

    /**
     * Suspend the company and all its users
     */
    public function suspend(): void
    {
        $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        // Suspend all users in this company except Super Admins
        $this->users()->whereHas('role', function($q) {
            $q->where('name', '!=', 'super_admin');
        })->update(['status' => 'suspended']);
    }

    /**
     * Deactivate the company and all its users
     */
    public function deactivate(): void
    {
        $this->update([
            'status' => 'inactive',
            'suspended_at' => null,
        ]);

        // Deactivate all users in this company except Super Admins
        $this->users()->whereHas('role', function($q) {
            $q->where('name', '!=', 'super_admin');
        })->update(['status' => 'inactive']);
    }
}
