<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Page;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamePageController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function store(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.canvas_width' => ['nullable', 'integer', 'min:300', 'max:20000'],
            'settings.canvas_height' => ['nullable', 'integer', 'min:300', 'max:20000'],
            'settings.grid_enabled' => ['nullable', 'boolean'],
            'settings.grid_cell_size' => ['nullable', 'integer', 'min:8', 'max:500'],
            'settings.lighting_type' => ['nullable', 'string', 'in:off,token,sources'],
        ]);

        $page = $this->gameService->createPage($game, $request->user(), $data);

        return response()->json([
            'message' => 'Page created.',
            'data' => $this->gameService->serializePage($page),
        ], 201);
    }

    public function update(Game $game, Page $page, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'array'],
            'settings.canvas_width' => ['sometimes', 'integer', 'min:300', 'max:20000'],
            'settings.canvas_height' => ['sometimes', 'integer', 'min:300', 'max:20000'],
            'settings.grid_enabled' => ['sometimes', 'boolean'],
            'settings.grid_cell_size' => ['sometimes', 'integer', 'min:8', 'max:500'],
            'settings.lighting_type' => ['sometimes', 'string', 'in:off,token,sources'],
        ]);

        $page = $this->gameService->updatePage($game, $page, $request->user(), $data);

        return response()->json([
            'message' => 'Page updated.',
            'data' => $this->gameService->serializePage($page),
        ]);
    }

    public function destroy(Game $game, Page $page, Request $request): JsonResponse
    {
        $this->gameService->deletePage($game, $page, $request->user());

        return response()->json([
            'message' => 'Page deleted.',
        ]);
    }

    public function activate(Game $game, Page $page, Request $request): JsonResponse
    {
        $game = $this->gameService->activatePage($game, $page, $request->user());

        return response()->json([
            'message' => 'Active page updated.',
            'data' => [
                'active_page_id' => $game->active_page_id,
            ],
        ]);
    }

    public function entities(Game $game, Page $page, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->gameService->pageEntities($game, $page, $request->user()),
        ]);
    }
}
