<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->gameService->search($data['search'] ?? null, $data['per_page'] ?? 15)
        );
    }

    public function myGames(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->gameService->myGames($request->user(), $data['per_page'] ?? 15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $game = $this->gameService->create($request->user(), $data);

        return response()->json([
            'message' => 'Game created.',
            'data' => $this->gameService->serializeGameDetail($game),
        ], 201);
    }

    public function show(Game $game): JsonResponse
    {
        return response()->json([
            'data' => $this->gameService->show($game),
        ]);
    }

    public function invite(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $invitations = $this->gameService->inviteUsers($game, $request->user(), $data['user_ids']);

        return response()->json([
            'message' => 'Invitations sent.',
            'data' => $invitations,
        ], 201);
    }

    public function join(Game $game, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Joined game.',
            'data' => $this->gameService->join($game, $request->user()),
        ]);
    }

    public function players(Game $game): JsonResponse
    {
        return response()->json([
            'data' => $this->gameService->players($game),
        ]);
    }
}
