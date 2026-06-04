<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loser extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'matric_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lostItems(): HasMany
    {
        return $this->hasMany(LostItem::class, 'loser_id', 'user_id');
    }

    public function reownershipClaims(): HasMany
    {
        return $this->hasMany(ReownershipClaim::class, 'loser_id', 'user_id');
    }
}
