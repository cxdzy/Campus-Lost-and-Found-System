<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostItem extends Model
{
    protected $primaryKey = 'item_id';
    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'loser_id',
        'image_path',
        'distinctive_features',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Loser::class, 'loser_id', 'user_id');
    }
}
