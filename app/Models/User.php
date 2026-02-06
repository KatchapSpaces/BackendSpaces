<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $with = [];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'email_verified_at',
        'company_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hasPermission($permission, $scope = null)
    {
        if ($this->role && $this->role->name === 'super_admin') {
            return true;
        }

        if (!$this->role) {
            return false;
        }

        $rolePermission = $this->role->permissions()->where('name', $permission)->first();

        if (!$rolePermission) {
            return false;
        }

        if ($scope && $rolePermission->pivot->scope !== $scope && $rolePermission->pivot->scope !== 'full') {
            return false;
        }

        return true;
    }

    public function hasRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }
}
