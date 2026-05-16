<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameChatController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function index(Game $game, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->gameService->listChatMessages($game, $request->user()),
        ]);
    }

    public function store(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->gameService->createChatMessage($game, $request->user(), $data['message']);

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->gameService->serializeChatMessage($message),
        ], 201);
    }

    public function rollDice(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'formula' => ['required', 'string', 'max:255'],
        ]);

        $message = $this->gameService->createDiceRollMessage($game, $request->user(), $data['formula']);

        return response()->json([
            'message' => 'Dice rolled.',
            'data' => $this->gameService->serializeChatMessage($message),
        ], 201);
    }
}
