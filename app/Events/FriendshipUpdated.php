<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendshipUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Friendship $friendship)
    {
        $this->friendship->loadMissing(['requester.avatarFile', 'addressee.avatarFile']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->friendship->requester_id),
            new PrivateChannel('users.'.$this->friendship->addressee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friendship.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->friendship->id,
            'status' => $this->friendship->status,
            'requester' => [
                'id' => $this->friendship->requester->id,
                'login' => $this->friendship->requester->login,
            ],
            'addressee' => [
                'id' => $this->friendship->addressee->id,
                'login' => $this->friendship->addressee->login,
            ],
            'responded_at' => $this->friendship->responded_at?->toISOString(),
        ];
    }
}
