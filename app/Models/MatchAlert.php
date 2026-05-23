<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'lost_item_id',
        'found_item_id',
        'match_score',
        'is_notified',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'float',
            'is_notified' => 'boolean',
        ];
    }

    public function lostItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'lost_item_id');
    }

    public function foundItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'found_item_id');
    }
}
