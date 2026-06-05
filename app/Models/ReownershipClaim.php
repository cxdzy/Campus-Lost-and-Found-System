<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReownershipClaim extends Model
{
    protected $table = 'reownership_claims';

    protected $fillable = [
        'found_item_id',
        'loser_id',
        'security_guard_id',
        'otp_code',
        'expires_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function foundItem(): BelongsTo
    {
        return $this->belongsTo(FoundItem::class, 'found_item_id', 'item_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Loser::class, 'loser_id', 'user_id');
    }

    public function securityGuard(): BelongsTo
    {
        return $this->belongsTo(User::class, 'security_guard_id');
    }
}
