<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Game;
use App\Providers\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameEntityController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function store(Game $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'in:token,shape,light-source,barrier'],
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'layer_id' => ['required', 'integer', 'exists:layers,id'],
            'width' => ['required', 'numeric'],
            'height' => ['required', 'numeric'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'file_id' => ['nullable', 'integer', 'exists:files,id'],
            'game_file_id' => ['nullable', 'integer', 'exists:game_files,id'],
            'disk' => ['nullable', 'string'],
            'game_file_path' => ['nullable', 'string', 'max:255'],
            'upload_file' => ['nullable', 'file'],
            'detailable_type' => ['nullable', 'string', 'max:255'],
            'detailable_id' => ['nullable', 'integer'],
            'shape' => ['nullable', 'array'],
            'shape.kind' => ['required_with:shape', 'string', 'in:line,circle,rectangle,rect'],
            'shape.color' => ['required_with:shape', 'string', 'max:32'],
            'shape.commands' => ['required_with:shape', 'array'],
            'shape.commands.*' => ['array'],
            'block' => ['nullable', 'boolean'],
            'controller_user_ids' => ['nullable', 'array'],
            'controller_user_ids.*' => ['integer', 'exists:users,id'],
            // Token lighting config controls vision, night vision and whether a token emits light itself.
            'token_settings' => ['nullable', 'array'],
            'token_settings.vision_enabled' => ['nullable', 'boolean'],
            'token_settings.all_players' => ['nullable', 'boolean'],
            'token_settings.player_user_ids' => ['nullable', 'array'],
            'token_settings.player_user_ids.*' => ['integer', 'exists:users,id'],
            'token_settings.night_vision_enabled' => ['nullable', 'boolean'],
            'token_settings.night_vision_range' => ['nullable', 'numeric', 'min:0'],
            'token_settings.light_enabled' => ['nullable', 'boolean'],
            'token_settings.light_radius' => ['nullable', 'numeric', 'min:0'],
            'light_source' => ['nullable', 'array'],
            'light_source.enabled' => ['nullable', 'boolean'],
            'light_source.radius' => ['nullable', 'numeric', 'min:0'],
            'barrier' => ['nullable', 'array'],
            'barrier.kind' => ['nullable', 'string', 'in:wall,door'],
            'barrier.is_open' => ['nullable', 'boolean'],
            'barrier.x1' => ['nullable', 'numeric'],
            'barrier.y1' => ['nullable', 'numeric'],
            'barrier.x2' => ['nullable', 'numeric'],
            'barrier.y2' => ['nullable', 'numeric'],
        ]);

        $entity = $this->gameService->createEntity(
            $game,
            $request->user(),
            $data,
            $request->file('upload_file')
        );

        return response()->json([
            'message' => 'Entity created.',
            'data' => $this->gameService->serializeEntity($entity),
        ], 201);
    }

    public function update(Game $game, Entity $entity, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entity_type' => ['sometimes', 'string', 'in:token,shape,light-source,barrier'],
            'page_id' => ['sometimes', 'integer', 'exists:pages,id'],
            'layer_id' => ['sometimes', 'integer', 'exists:layers,id'],
            'width' => ['sometimes', 'numeric'],
            'height' => ['sometimes', 'numeric'],
            'x' => ['sometimes', 'numeric'],
            'y' => ['sometimes', 'numeric'],
            'file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'detailable_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'detailable_id' => ['sometimes', 'nullable', 'integer'],
            'block' => ['sometimes', 'boolean'],
            'controller_user_ids' => ['sometimes', 'array'],
            'controller_user_ids.*' => ['integer', 'exists:users,id'],
            'token_settings' => ['sometimes', 'array'],
            'token_settings.vision_enabled' => ['sometimes', 'boolean'],
            'token_settings.all_players' => ['sometimes', 'boolean'],
            'token_settings.player_user_ids' => ['sometimes', 'array'],
            'token_settings.player_user_ids.*' => ['integer', 'exists:users,id'],
            'token_settings.night_vision_enabled' => ['sometimes', 'boolean'],
            'token_settings.night_vision_range' => ['sometimes', 'numeric', 'min:0'],
            'token_settings.light_enabled' => ['sometimes', 'boolean'],
            'token_settings.light_radius' => ['sometimes', 'numeric', 'min:0'],
            'light_source' => ['sometimes', 'array'],
            'light_source.enabled' => ['sometimes', 'boolean'],
            'light_source.radius' => ['sometimes', 'numeric', 'min:0'],
            'barrier' => ['sometimes', 'array'],
            'barrier.kind' => ['sometimes', 'string', 'in:wall,door'],
            'barrier.is_open' => ['sometimes', 'boolean'],
            'barrier.x1' => ['sometimes', 'numeric'],
            'barrier.y1' => ['sometimes', 'numeric'],
            'barrier.x2' => ['sometimes', 'numeric'],
            'barrier.y2' => ['sometimes', 'numeric'],
        ]);

        $entity = $this->gameService->updateEntity($game, $entity, $request->user(), $data);

        return response()->json([
            'message' => 'Entity updated.',
            'data' => $this->gameService->serializeEntity($entity),
        ]);
    }

    public function destroy(Game $game, Entity $entity, Request $request): JsonResponse
    {
        $this->gameService->deleteEntity($game, $entity, $request->user());

        return response()->json([
            'message' => 'Entity deleted.',
        ]);
    }
}
