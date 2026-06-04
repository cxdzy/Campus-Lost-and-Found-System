<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FoundItem extends Model
{
    protected $primaryKey = 'item_id';
    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'finder_id',
        'image_path',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function finder(): BelongsTo
    {
        return $this->belongsTo(Finder::class, 'finder_id', 'user_id');
    }

    public function aiTags(): HasMany
    {
        return $this->hasMany(AiTag::class, 'found_item_id', 'item_id');
    }

    public function reownershipClaim(): HasOne
    {
        return $this->hasOne(ReownershipClaim::class, 'found_item_id', 'item_id');
    }
}
