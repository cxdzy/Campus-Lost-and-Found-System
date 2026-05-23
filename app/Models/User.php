<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'matric_number',
        'telegram_chat_id',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'claimant_user_id');
    }

    public function handledClaims(): HasMany
    {
        return $this->hasMany(Claim::class, 'security_guard_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function isSecurity(): bool
    {
        return $this->role === 'Security';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isSecurity();
    }
}
