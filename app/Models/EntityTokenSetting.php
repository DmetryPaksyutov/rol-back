<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EntityTokenSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'vision_enabled',
        'all_players',
        'player_user_ids',
        'night_vision_enabled',
        'night_vision_range',
        'light_enabled',
        'light_radius',
    ];

    protected function casts(): array
    {
        return [
            'vision_enabled' => 'boolean',
            'all_players' => 'boolean',
            'player_user_ids' => 'array',
            'night_vision_enabled' => 'boolean',
            'night_vision_range' => 'decimal:2',
            'light_enabled' => 'boolean',
            'light_radius' => 'decimal:2',
        ];
    }

    public function entity(): MorphOne
    {
        return $this->morphOne(Entity::class, 'detailable');
    }
}
