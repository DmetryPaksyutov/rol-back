<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameRealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public string $type,
        public array $payload
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('games.'.$this->game->key)];
    }

    public function broadcastAs(): string
    {
        return 'game.realtime';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'game_key' => $this->game->key,
            'payload' => $this->payload,
        ];
    }
}
