<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('games.{gameKey}', function (User $user, string $gameKey): bool {
    $game = Game::query()->where('key', $gameKey)->first();

    if (! $game) {
        return false;
    }

    if ((int) $game->owner === (int) $user->id) {
        return true;
    }

    return $game->players()->where('users.id', $user->id)->exists();
});
