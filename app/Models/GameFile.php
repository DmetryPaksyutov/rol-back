<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'kind',
        'name',
        'path',
        'file_id',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
