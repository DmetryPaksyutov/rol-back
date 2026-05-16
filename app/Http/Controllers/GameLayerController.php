<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Layer;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameLayerController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function store(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:light,token,map'],
            'level' => ['required', 'integer'],
            'visible' => ['required', 'boolean'],
            'interactive' => ['required', 'boolean'],
        ]);

        $layer = $this->gameService->createLayer($game, $request->user(), $data);

        return response()->json([
            'message' => 'Layer created.',
            'data' => $this->gameService->serializeLayer($layer),
        ], 201);
    }

    public function update(Game $game, Layer $layer, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:light,token,map'],
            'level' => ['sometimes', 'integer'],
            'visible' => ['sometimes', 'boolean'],
            'interactive' => ['sometimes', 'boolean'],
        ]);

        $layer = $this->gameService->updateLayer($game, $layer, $request->user(), $data);

        return response()->json([
            'message' => 'Layer updated.',
            'data' => $this->gameService->serializeLayer($layer),
        ]);
    }

    public function destroy(Game $game, Layer $layer, Request $request): JsonResponse
    {
        $this->gameService->deleteLayer($game, $layer, $request->user());

        return response()->json([
            'message' => 'Layer deleted.',
        ]);
    }
}
