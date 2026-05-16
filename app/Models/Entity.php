<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_type',
        'page_id',
        'layer_id',
        'width',
        'height',
        'x',
        'y',
        'file_id',
        'block',
        'controller_user_ids',
        'detailable_type',
        'detailable_id',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'x' => 'decimal:2',
            'y' => 'decimal:2',
            'block' => 'boolean',
            'controller_user_ids' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function detailable(): MorphTo
    {
        return $this->morphTo();
    }
}
