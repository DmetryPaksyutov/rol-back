<?php

namespace App\Providers;

use App\Events\FriendshipUpdated;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FriendshipService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function listFriends(User $user): Collection
    {
        $friendships = Friendship::query()
            ->with(['requester.avatarFile', 'addressee.avatarFile'])
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(function ($query) use ($user) {
                $query
                    ->where('requester_id', $user->id)
                    ->orWhere('addressee_id', $user->id);
            })
            ->get();

        return $friendships->map(function (Friendship $friendship) use ($user) {
            $friend = $friendship->requester_id === $user->id
                ? $friendship->addressee
                : $friendship->requester;

            return [
                'id' => $friend->id,
                'login' => $friend->login,
                'email' => $friend->email,
                'avatar' => $friend->avatarFile ? [
                    'id' => $friend->avatarFile->id,
                    'url' => route('files.show', $friend->avatarFile),
                ] : null,
            ];
        })->values();
    }

    public function notifications(User $user): array
    {
        $incoming = Friendship::query()
            ->with('requester.avatarFile')
            ->where('addressee_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => $this->transform($friendship, $user));

        $outgoing = Friendship::query()
            ->with('addressee.avatarFile')
            ->where('requester_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => $this->transform($friendship, $user));

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    public function invite(User $requester, User $addressee): Friendship
    {
        if ($requester->is($addressee)) {
            throw ValidationException::withMessages([
                'user_id' => ['Нельзя отправить приглашение самому себе.'],
            ]);
        }

        $existing = Friendship::query()
            ->where(function ($query) use ($requester, $addressee) {
                $query
                    ->where('requester_id', $requester->id)
                    ->where('addressee_id', $addressee->id);
            })
            ->orWhere(function ($query) use ($requester, $addressee) {
                $query
                    ->where('requester_id', $addressee->id)
                    ->where('addressee_id', $requester->id);
            })
            ->first();

        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) {
                throw ValidationException::withMessages([
                    'user_id' => ['Пользователь уже находится в списке друзей.'],
                ]);
            }

            if (
                $existing->requester_id === $addressee->id
                && $existing->addressee_id === $requester->id
                && $existing->status === Friendship::STATUS_PENDING
            ) {
                throw ValidationException::withMessages([
                    'user_id' => ['У вас уже есть входящее приглашение от этого пользователя.'],
                ]);
            }

            $existing->fill([
                'requester_id' => $requester->id,
                'addressee_id' => $addressee->id,
                'status' => Friendship::STATUS_PENDING,
                'responded_at' => null,
            ])->save();

            $friendship = $existing->fresh(['requester.avatarFile', 'addressee.avatarFile']);
        } else {
            $friendship = Friendship::create([
                'requester_id' => $requester->id,
                'addressee_id' => $addressee->id,
                'status' => Friendship::STATUS_PENDING,
            ])->load(['requester.avatarFile', 'addressee.avatarFile']);
        }

        $this->dispatchBroadcastSafely(new FriendshipUpdated($friendship));
        $friendship->load(['requester.avatarFile', 'addressee.avatarFile']);
        $payload = $this->transform($friendship, $addressee);
        $payload['direction'] = 'incoming';
        $this->notificationService->publish(
            $addressee->id,
            'friendship.invitation.received',
            $payload,
            NotificationService::STREAM_FRIENDSHIPS
        );

        return $friendship;
    }

    public function respond(User $user, Friendship $friendship, string $status): Friendship
    {
        if ($friendship->addressee_id !== $user->id) {
            throw ValidationException::withMessages([
                'friendship' => ['Вы не можете обработать это приглашение.'],
            ]);
        }

        if ($friendship->status !== Friendship::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'friendship' => ['Приглашение уже обработано ранее.'],
            ]);
        }

        $friendship->update([
            'status' => $status,
            'responded_at' => now(),
        ]);

        $friendship->load(['requester.avatarFile', 'addressee.avatarFile']);

        $this->dispatchBroadcastSafely(new FriendshipUpdated($friendship));
        $payload = $this->transform($friendship, $friendship->requester);
        $payload['direction'] = 'outgoing';
        $this->notificationService->publish(
            $friendship->requester_id,
            'friendship.invitation.'.$status,
            $payload,
            NotificationService::STREAM_FRIENDSHIPS
        );

        return $friendship;
    }

    protected function transform(Friendship $friendship, User $currentUser): array
    {
        $counterparty = $friendship->requester_id === $currentUser->id
            ? $friendship->addressee
            : $friendship->requester;

        return [
            'id' => $friendship->id,
            'status' => $friendship->status,
            'responded_at' => $friendship->responded_at,
            'created_at' => $friendship->created_at,
            'user' => [
                'id' => $counterparty->id,
                'login' => $counterparty->login,
                'email' => $counterparty->email,
                'avatar' => $counterparty->avatarFile ? [
                    'id' => $counterparty->avatarFile->id,
                    'url' => route('files.show', $counterparty->avatarFile),
                ] : null,
            ],
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
