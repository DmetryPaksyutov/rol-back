<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner',
        'description',
        'key',
        'image',
        'active_page_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner');
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image');
    }

    public function activePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'active_page_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class);
    }

    public function gameFiles(): HasMany
    {
        return $this->hasMany(GameFile::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class, 'page_id', 'active_page_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_players')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(GameInvitation::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(GameChatMessage::class);
    }
}
