<?php

namespace App\Providers;

use App\Events\UserNotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public const STREAM_GENERAL = 'general';
    public const STREAM_FRIENDSHIPS = 'friendships';
    public const STREAM_GAMES = 'games';

    public function listForUser(User $user, int $perPage = 30, ?string $stream = null): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->when($stream, fn ($query) => $query->where('stream', $stream))
            ->latest()
            ->paginate($perPage);
    }

    public function publish(
        int $userId,
        string $type,
        array $payload,
        string $stream = self::STREAM_GENERAL
    ): UserNotification {
        $notification = UserNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'stream' => $stream,
            'payload' => $payload,
        ]);

        $this->dispatchBroadcastSafely(new UserNotificationEvent($userId, $type, [
            'notification' => $this->serialize($notification),
        ]));

        return $notification;
    }

    public function markAsRead(User $user, UserNotification $notification): UserNotification
    {
        abort_if((int) $notification->user_id !== (int) $user->id, 403, 'Notification does not belong to the current user.');

        $notification->update([
            'read_at' => now(),
        ]);

        return $notification->fresh();
    }

    public function delete(User $user, UserNotification $notification): int
    {
        abort_if((int) $notification->user_id !== (int) $user->id, 403, 'Notification does not belong to the current user.');

        $notificationId = $notification->id;
        $notification->delete();

        broadcast(new UserNotificationEvent($user->id, 'notification.deleted', [
            'notification_id' => $notificationId,
        ]));

        return $notificationId;
    }

    public function clear(User $user): void
    {
        UserNotification::query()
            ->where('user_id', $user->id)
            ->delete();

        broadcast(new UserNotificationEvent($user->id, 'notification.cleared', [
            'user_id' => $user->id,
        ]));
    }

    public function serialize(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'stream' => $notification->stream,
            'payload' => $notification->payload,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }

    protected function dispatchBroadcastSafely(object $event): void
    {
        try {
            $pending = broadcast($event);
            unset($pending);
        } catch (\Throwable) {
            // Realtime delivery should not break core HTTP flows.
        }
    }
}
