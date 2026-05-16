<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EntityBarrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'kind',
        'is_open',
        'x1',
        'y1',
        'x2',
        'y2',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'x1' => 'decimal:2',
            'y1' => 'decimal:2',
            'x2' => 'decimal:2',
            'y2' => 'decimal:2',
        ];
    }

    public function entity(): MorphOne
    {
        return $this->morphOne(Entity::class, 'detailable');
    }
}
