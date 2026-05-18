<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameFile;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameFileController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function index(Game $game): JsonResponse
    {
        return response()->json([
            'data' => $this->gameService->listGameFiles($game),
        ]);
    }

    public function store(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'string', 'in:image,folder'],
            'name' => ['nullable', 'string', 'max:255'],
            'disk' => ['required_unless:kind,folder', 'string'],
            'path' => ['nullable', 'string', 'max:255'],
            'files' => ['required_unless:kind,folder', 'array', 'min:1'],
            'files.*' => ['required', 'file'],
        ]);

        $files = ($data['kind'] ?? 'image') === 'folder'
            ? collect([
                $this->gameService->createGameFolder(
                    $game,
                    $request->user(),
                    $data['name'] ?? 'Новая папка',
                    $data['path'] ?? null
                ),
            ])
            : $this->gameService->uploadGameFiles(
                $game,
                $request->user(),
                $data['disk'],
                $request->file('files', []),
                $data['path'] ?? null
            );

        return response()->json([
            'message' => 'Game files uploaded.',
            'data' => $files->map(fn (GameFile $gameFile) => $this->gameService->serializeGameFile($gameFile)),
        ], 201);
    }

    public function update(Game $game, GameFile $gameFile, Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['sometimes', 'string', 'in:image,folder'],
            'name' => ['sometimes', 'string', 'max:255'],
            'path' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $gameFile = $this->gameService->updateGameFile($game, $gameFile, $request->user(), $data);

        return response()->json([
            'message' => 'Game file updated.',
            'data' => $this->gameService->serializeGameFile($gameFile),
        ]);
    }

    public function destroy(Game $game, GameFile $gameFile, Request $request): JsonResponse
    {
        $this->gameService->deleteGameFile($game, $gameFile, $request->user());

        return response()->json([
            'message' => 'Game file deleted.',
        ]);
    }
}
