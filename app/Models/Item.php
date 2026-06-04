<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title_description',
        'latitude',
        'longitude',
        'location_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function foundItem(): HasOne
    {
        return $this->hasOne(FoundItem::class, 'item_id');
    }

    public function lostItem(): HasOne
    {
        return $this->hasOne(LostItem::class, 'item_id');
    }

    public function matchAlertsAsLost(): HasMany
    {
        return $this->hasMany(MatchAlert::class, 'lost_item_id');
    }

    public function matchAlertsAsFound(): HasMany
    {
        return $this->hasMany(MatchAlert::class, 'found_item_id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }
}
