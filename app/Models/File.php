<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'original_name',
        'name',
        'hash',
        'extension',
        'mime_type',
        'size',
    ];

    public function usersWithAvatar(): HasMany
    {
        return $this->hasMany(User::class, 'avatar_file_id');
    }
}
