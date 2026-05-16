<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EntityLightSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled',
        'radius',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'radius' => 'decimal:2',
        ];
    }

    public function entity(): MorphOne
    {
        return $this->morphOne(Entity::class, 'detailable');
    }
}
