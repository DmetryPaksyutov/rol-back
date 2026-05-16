<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'canvas_width',
        'canvas_height',
        'grid_enabled',
        'grid_cell_size',
        'lighting_type',
    ];

    protected function casts(): array
    {
        return [
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
            'grid_enabled' => 'boolean',
            'grid_cell_size' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
