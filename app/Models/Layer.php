<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Layer extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'type',
        'level',
        'visible',
        'interactive',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'interactive' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }
}
