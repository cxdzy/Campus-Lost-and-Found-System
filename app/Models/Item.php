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
        'user_id',
        'category_id',
        'type',
        'title_description',
        'latitude',
        'longitude',
        'location_name',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function aiTags(): HasMany
    {
        return $this->hasMany(AiTag::class);
    }

    public function matchAlertsAsLost(): HasMany
    {
        return $this->hasMany(MatchAlert::class, 'lost_item_id');
    }

    public function matchAlertsAsFound(): HasMany
    {
        return $this->hasMany(MatchAlert::class, 'found_item_id');
    }

    public function claim(): HasOne
    {
        return $this->hasOne(Claim::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }
}
