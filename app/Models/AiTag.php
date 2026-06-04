<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'found_item_id',
        'tag_name',
        'confidence_level',
    ];

    protected function casts(): array
    {
        return [
            'confidence_level' => 'float',
        ];
    }

    public function foundItem(): BelongsTo
    {
        return $this->belongsTo(FoundItem::class, 'found_item_id', 'item_id');
    }
}
