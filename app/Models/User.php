<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'matric_number',
        'telegram_chat_id',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function finder(): HasOne
    {
        return $this->hasOne(Finder::class, 'user_id');
    }

    public function loser(): HasOne
    {
        return $this->hasOne(Loser::class, 'user_id');
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

    public function isUser(): bool
    {
        return $this->role === 'User';
    }
}
