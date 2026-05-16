<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EntityDrawing extends Model
{
    use HasFactory;

    protected $fillable = [
        'kind',
        'color',
        'commands',
    ];

    protected function casts(): array
    {
        return [
            'commands' => 'array',
        ];
    }

    public function entity(): MorphOne
    {
        return $this->morphOne(Entity::class, 'detailable');
    }
}
