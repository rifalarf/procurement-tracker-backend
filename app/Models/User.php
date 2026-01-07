<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'is_active',
        'color',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is AVP
     */
    public function isAvp(): bool
    {
        return $this->role === 'avp';
    }

    /**
     * Check if user is Staff
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Departments relationship (many-to-many)
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'user_departments');
    }

    /**
     * Buyer profile relationship (one-to-one)
     */
    public function buyer()
    {
        return $this->hasOne(Buyer::class);
    }
}
