<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'tag_name',
        'confidence_level',
    ];

    protected function casts(): array
    {
        return [
            'confidence_level' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
