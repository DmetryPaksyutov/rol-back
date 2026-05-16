<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Providers\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function __construct(private readonly FriendshipService $friendshipService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $targetUser = isset($data['user_id'])
            ? User::query()->findOrFail($data['user_id'])
            : $request->user();

        return response()->json([
            'data' => $this->friendshipService->listFriends($targetUser),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(
            $this->friendshipService->notifications($request->user())
        );
    }

    public function store(User $user, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Приглашение отправлено.',
            'data' => $this->friendshipService->invite($request->user(), $user),
        ], 201);
    }

    public function accept(Friendship $friendship, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Приглашение принято.',
            'data' => $this->friendshipService->respond(
                $request->user(),
                $friendship,
                Friendship::STATUS_ACCEPTED
            ),
        ]);
    }

    public function reject(Friendship $friendship, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Приглашение отклонено.',
            'data' => $this->friendshipService->respond(
                $request->user(),
                $friendship,
                Friendship::STATUS_REJECTED
            ),
        ]);
    }
}
