<?php

namespace App\Providers;

use App\Events\GameRealtimeEvent;
use App\Models\EntityBarrier;
use App\Models\Entity;
use App\Models\EntityDrawing;
use App\Models\EntityLightSource;
use App\Models\EntityTokenSetting;
use App\Models\File;
use App\Models\Game;
use App\Models\GameChatMessage;
use App\Models\GameFile;
use App\Models\GameInvitation;
use App\Models\GamePlayer;
use App\Models\Layer;
use App\Models\Page;
use App\Models\PageSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameService
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly NotificationService $notificationService,
        private readonly DiceParsingService $diceParsingService
    )
    {
    }

    public function search(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Game::query()
            ->with(['ownerUser.avatarFile', 'imageFile', 'activePage.settings'])
            ->when($search, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('key', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function myGames(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Game::query()
            ->with(['ownerUser.avatarFile', 'imageFile', 'activePage.settings'])
            ->where(function ($query) use ($user) {
                $query
                    ->where('owner', $user->id)
                    ->orWhereHas('players', function ($builder) use ($user) {
                        $builder->where('users.id', $user->id);
                    });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function create(User $owner, array $data): Game
    {
        return DB::transaction(function () use ($owner, $data): Game {
            $game = Game::create([
                'name' => $data['name'],
                'owner' => $owner->id,
                'description' => $data['description'] ?? null,
                'key' => $this->generateUniqueKey(),
                'image' => $data['image'] ?? null,
            ]);

            $page = $game->pages()->create([
                'name' => 'New page',
                'path' => '/',
            ]);
            $page->settings()->create($this->defaultPageSettings());

            $game->layers()->createMany([
                [
                    'name' => 'Tokens',
                    'type' => 'token',
                    'level' => 100,
                    'visible' => true,
                    'interactive' => true,
                ],
                [
                    'name' => 'GM',
                    'type' => 'token',
                    'level' => 200,
                    'visible' => false,
                    'interactive' => false,
                ],
                [
                    'name' => 'Map',
                    'type' => 'map',
                    'level' => 0,
                    'visible' => true,
                    'interactive' => false,
                ],
                [
                    'name' => 'Lighting',
                    'type' => 'light',
                    'level' => 150,
                    'visible' => true,
                    'interactive' => false,
                ],
            ]);

            $game->active_page_id = $page->id;
            $game->save();

            GamePlayer::firstOrCreate([
                'game_id' => $game->id,
                'user_id' => $owner->id,
            ]);

            return $game->fresh([
                'ownerUser.avatarFile',
                'imageFile',
                'activePage.settings',
                'pages.settings',
                'layers',
            ]);
        });
    }

    public function inviteUsers(Game $game, User $inviter, array $userIds): Collection
    {
        $this->assertOwner($game, $inviter);

        return collect($userIds)->map(function (int $userId) use ($game, $inviter) {
            if ($userId === $inviter->id) {
                throw ValidationException::withMessages([
                    'user_ids' => ['You cannot invite yourself to the game.'],
                ]);
            }

            $invitation = GameInvitation::query()->updateOrCreate(
                [
                    'game_id' => $game->id,
                    'invited_user_id' => $userId,
                ],
                [
                    'inviter_id' => $inviter->id,
                    'status' => GameInvitation::STATUS_PENDING,
                    'responded_at' => null,
                ]
            );

            $this->notificationService->publish(
                $userId,
                'game.invitation.received',
                [
                    'game' => $this->serializeGameSummary($game->fresh(['ownerUser.avatarFile', 'imageFile', 'activePage.settings'])),
                    'invitation_id' => $invitation->id,
                    'inviter_id' => $inviter->id,
                    'status' => $invitation->status,
                ],
                NotificationService::STREAM_GAMES
            );

            return $invitation->load(['invitedUser.avatarFile']);
        });
    }

    public function join(Game $game, User $user): array
    {
        GamePlayer::firstOrCreate([
            'game_id' => $game->id,
            'user_id' => $user->id,
        ]);

        $game->loadMissing([
            'ownerUser.avatarFile',
            'imageFile',
            'activePage.settings',
            'pages.settings',
            'layers',
            'gameFiles.file',
        ]);

        $activePage = $game->activePage;
        $entities = $activePage
            ? $activePage->entities()->with(['file', 'layer', 'detailable'])->get()
            : collect();

        $payload = [
            'game' => $this->serializeGameDetail($game),
            'is_owner' => $this->isOwner($game, $user),
            'active_page' => $activePage ? $this->serializePage($activePage) : null,
            'layers' => $game->layers->sortBy('level')->values()->map(fn (Layer $layer) => $this->serializeLayer($layer)),
            'entities' => $entities->map(fn (Entity $entity) => $this->serializeEntity($entity)),
            'chat_messages' => $game->chatMessages()->with('user.avatarFile')->latest()->limit(50)->get()
                ->reverse()->values()->map(fn (GameChatMessage $message) => $this->serializeChatMessage($message)),
            'players' => $this->players($game),
            'game_files' => $game->gameFiles->map(fn (GameFile $gameFile) => $this->serializeGameFile($gameFile)),
        ];

        if ($this->isOwner($game, $user)) {
            $payload['pages'] = $game->pages->map(fn (Page $page) => $this->serializePage($page));
        }

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'game.joined', [
            'user_id' => $user->id,
            'players' => $this->players($game),
        ]));

        return $payload;
    }

    public function players(Game $game): Collection
    {
        return $game->players()->with('avatarFile')->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'login' => $user->login,
                'avatar' => $user->avatarFile ? [
                    'id' => $user->avatarFile->id,
                    'url' => route('files.show', $user->avatarFile),
                ] : null,
            ];
        });
    }

    public function show(Game $game): array
    {
        $game->loadMissing(['ownerUser.avatarFile', 'imageFile', 'activePage.settings']);

        return $this->serializeGameSummary($game);
    }

    public function createPage(Game $game, User $user, array $data): Page
    {
        $this->assertOwner($game, $user);

        $page = $game->pages()->create([
            'name' => $data['name'],
            'path' => $data['path'] ?? '/',
        ]);
        $page->settings()->create(array_merge($this->defaultPageSettings(), $data['settings'] ?? []));

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'page.created', [
            'page' => $this->serializePage($page->fresh('settings')),
        ]));

        return $page->fresh('settings');
    }

    public function updatePage(Game $game, Page $page, User $user, array $data): Page
    {
        $this->assertOwner($game, $user);
        $this->assertGamePage($game, $page);

        $page->update([
            'name' => $data['name'] ?? $page->name,
            'path' => $data['path'] ?? $page->path,
        ]);

        if (array_key_exists('settings', $data)) {
            $page->settings()->updateOrCreate(
                ['page_id' => $page->id],
                array_merge($this->defaultPageSettings(), $data['settings'])
            );
        }

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'page.updated', [
            'page' => $this->serializePage($page->fresh('settings')),
        ]));

        return $page->fresh('settings');
    }

    public function deletePage(Game $game, Page $page, User $user): void
    {
        $this->assertOwner($game, $user);
        $this->assertGamePage($game, $page);

        if ($game->pages()->count() <= 1) {
            throw ValidationException::withMessages([
                'page' => ['At least one page must remain in the game.'],
            ]);
        }

        DB::transaction(function () use ($game, $page): void {
            $pageId = $page->id;
            $page->delete();

            if ($game->active_page_id === $pageId) {
                $newActive = $game->pages()->oldest()->first();
                $game->update(['active_page_id' => $newActive?->id]);
            }
        });

        $game->refresh()->load('activePage.settings');

        $activeEntities = $game->activePage
            ? $game->activePage->entities()->with(['file', 'layer', 'detailable'])->get()
            : collect();

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'page.deleted', [
            'page_id' => $page->id,
            'active_page_id' => $game->active_page_id,
            'active_page' => $game->activePage ? $this->serializePage($game->activePage) : null,
            'entities' => $activeEntities->map(fn (Entity $entity) => $this->serializeEntity($entity)),
        ]));
    }

    public function activatePage(Game $game, Page $page, User $user): Game
    {
        $this->assertOwner($game, $user);
        $this->assertGamePage($game, $page);

        $game->update(['active_page_id' => $page->id]);

        $entities = $page->entities()->with(['file', 'layer', 'detailable'])->get();

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game->fresh(['activePage.settings']), 'page.activated', [
            'active_page' => $this->serializePage($page),
            'entities' => $entities->map(fn (Entity $entity) => $this->serializeEntity($entity)),
        ]));

        return $game->fresh(['activePage.settings']);
    }

    public function createLayer(Game $game, User $user, array $data): Layer
    {
        $this->assertOwner($game, $user);

        $layer = $game->layers()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'token',
            'level' => $data['level'],
            'visible' => $data['visible'],
            'interactive' => $data['interactive'],
        ]);

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'layer.created', [
            'layer' => $this->serializeLayer($layer),
        ]));

        return $layer;
    }

    public function updateLayer(Game $game, Layer $layer, User $user, array $data): Layer
    {
        $this->assertOwner($game, $user);
        $this->assertGameLayer($game, $layer);

        $layer->update([
            'name' => $data['name'] ?? $layer->name,
            'type' => $data['type'] ?? $layer->type,
            'level' => $data['level'] ?? $layer->level,
            'visible' => $data['visible'] ?? $layer->visible,
            'interactive' => $data['interactive'] ?? $layer->interactive,
        ]);

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'layer.updated', [
            'layer' => $this->serializeLayer($layer->fresh()),
        ]));

        return $layer->fresh();
    }

    public function deleteLayer(Game $game, Layer $layer, User $user): void
    {
        $this->assertOwner($game, $user);
        $this->assertGameLayer($game, $layer);

        $layerId = $layer->id;
        $deletedEntityIds = $layer->entities()->pluck('id');
        $layer->delete();

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'layer.deleted', [
            'layer_id' => $layerId,
            'deleted_entity_ids' => $deletedEntityIds,
        ]));
    }

    public function listGameFiles(Game $game): Collection
    {
        return $game->gameFiles()->with('file')->get()
            ->map(fn (GameFile $gameFile) => $this->serializeGameFile($gameFile));
    }

    public function uploadGameFiles(Game $game, User $user, string $disk, array $uploadedFiles, ?string $path = null): Collection
    {
        $this->assertParticipant($game, $user);

        $files = $this->fileService->bulkUpload($uploadedFiles, $disk);
        $now = now();

        $records = $files->map(fn (File $file) => [
            'game_id' => $game->id,
            'name' => $file->original_name,
            'path' => $path,
            'file_id' => $file->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        GameFile::query()->insert($records);

        $gameFiles = $game->gameFiles()->with('file')->latest()->limit(count($records))->get()->reverse()->values();

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'game-file.created', [
            'files' => $gameFiles->map(fn (GameFile $gameFile) => $this->serializeGameFile($gameFile)),
        ]));

        return $gameFiles;
    }

    public function updateGameFile(Game $game, GameFile $gameFile, User $user, array $data): GameFile
    {
        $this->assertParticipant($game, $user);
        $this->assertGameFile($game, $gameFile);

        $gameFile->update([
            'name' => $data['name'] ?? $gameFile->name,
            'path' => $data['path'] ?? $gameFile->path,
        ]);

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'game-file.updated', [
            'file' => $this->serializeGameFile($gameFile->fresh('file')),
        ]));

        return $gameFile->fresh('file');
    }

    public function deleteGameFile(Game $game, GameFile $gameFile, User $user): void
    {
        $this->assertParticipant($game, $user);
        $this->assertGameFile($game, $gameFile);

        $gameFileId = $gameFile->id;
        DB::transaction(function () use ($gameFile): void {
            $file = $gameFile->file;
            $gameFile->delete();

            if ($file) {
                $this->fileService->delete($file);
            }
        });

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'game-file.deleted', [
            'game_file_id' => $gameFileId,
        ]));
    }

    public function pageEntities(Game $game, Page $page, User $user): Collection
    {
        $this->assertParticipant($game, $user);
        $this->assertGamePage($game, $page);

        if (! $this->isOwner($game, $user) && $game->active_page_id !== $page->id) {
            throw ValidationException::withMessages([
                'page' => ['Only the active page is available for non-owners.'],
            ]);
        }

        return $page->entities()->with(['file', 'layer', 'detailable'])->get()
            ->map(fn (Entity $entity) => $this->serializeEntity($entity));
    }

    public function createEntity(Game $game, User $user, array $data, ?UploadedFile $uploadedFile = null): Entity
    {
        $this->assertParticipant($game, $user);

        $page = Page::query()->findOrFail($data['page_id']);
        $layer = Layer::query()->findOrFail($data['layer_id']);
        $this->assertGamePage($game, $page);
        $this->assertGameLayer($game, $layer);

        $fileId = $data['file_id'] ?? null;

        if ($uploadedFile) {
            $disk = $data['disk'] ?? 'public';
            $uploaded = $this->uploadGameFiles($game, $user, $disk, [$uploadedFile], $data['game_file_path'] ?? null)->first();
            $fileId = $uploaded?->file_id;
        } elseif (! empty($data['game_file_id'])) {
            $gameFile = GameFile::query()->with('file')->findOrFail($data['game_file_id']);
            $this->assertGameFile($game, $gameFile);
            $fileId = $gameFile->file_id;
        }

        $entityType = $data['entity_type'] ?? (! empty($data['shape']) ? 'shape' : 'token');
        $drawing = null;
        $tokenSetting = null;
        $lightSource = null;
        $barrier = null;

        if (! empty($data['shape'])) {
            $shape = $data['shape'];
            $drawing = EntityDrawing::create([
                'kind' => $shape['kind'] === 'rect' ? 'rectangle' : $shape['kind'],
                'color' => $shape['color'],
                'commands' => $shape['commands'],
            ]);
            $fileId = null;
        }

        if ($entityType === 'token') {
            // Token lighting is stored separately so the client can treat tokens, light sources and barriers uniformly.
            $tokenSetting = EntityTokenSetting::create([
                'vision_enabled' => data_get($data, 'token_settings.vision_enabled', false),
                'all_players' => data_get($data, 'token_settings.all_players', false),
                'player_user_ids' => data_get($data, 'token_settings.player_user_ids', []),
                'night_vision_enabled' => data_get($data, 'token_settings.night_vision_enabled', false),
                'night_vision_range' => data_get($data, 'token_settings.night_vision_range', 0),
                'light_enabled' => data_get($data, 'token_settings.light_enabled', false),
                'light_radius' => data_get($data, 'token_settings.light_radius', 0),
            ]);
        } elseif ($entityType === 'light-source') {
            $lightSource = EntityLightSource::create([
                'enabled' => data_get($data, 'light_source.enabled', true),
                'radius' => data_get($data, 'light_source.radius', 6),
            ]);
            $fileId = null;
        } elseif ($entityType === 'barrier') {
            $barrier = EntityBarrier::create([
                'kind' => data_get($data, 'barrier.kind', 'wall'),
                'is_open' => data_get($data, 'barrier.is_open', false),
                'x1' => data_get($data, 'barrier.x1', $data['x']),
                'y1' => data_get($data, 'barrier.y1', $data['y']),
                'x2' => data_get($data, 'barrier.x2', $data['x'] + $data['width']),
                'y2' => data_get($data, 'barrier.y2', $data['y'] + $data['height']),
            ]);
            $fileId = null;
        }

        $entityData = [
            'name' => $data['name'] ?? null,
            'entity_type' => $entityType,
            'page_id' => $page->id,
            'layer_id' => $layer->id,
            'width' => $data['width'],
            'height' => $data['height'],
            'x' => $data['x'],
            'y' => $data['y'],
            'file_id' => $fileId,
            'detailable_type' => $drawing
                ? EntityDrawing::class
                : ($tokenSetting
                    ? EntityTokenSetting::class
                    : ($lightSource
                        ? EntityLightSource::class
                        : ($barrier
                            ? EntityBarrier::class
                            : ($data['detailable_type'] ?? null)))),
            'detailable_id' => $drawing?->id
                ?? $tokenSetting?->id
                ?? $lightSource?->id
                ?? $barrier?->id
                ?? ($data['detailable_id'] ?? null),
        ];

        if ($this->isOwner($game, $user)) {
            $entityData['block'] = $data['block'] ?? false;
            $entityData['controller_user_ids'] = $data['controller_user_ids'] ?? [];
        }

        $entity = Entity::create($entityData)->load(['file', 'layer', 'detailable']);

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'entity.created', [
            'entity' => $this->serializeEntity($entity),
        ]));

        return $entity;
    }

    public function updateEntity(Game $game, Entity $entity, User $user, array $data): Entity
    {
        $this->assertParticipant($game, $user);
        $this->assertEntityGame($game, $entity);

        if (! $this->canEditEntity($game, $entity, $user)) {
            throw ValidationException::withMessages([
                'entity' => ['You do not have permission to update this entity.'],
            ]);
        }

        $update = [
            'name' => $data['name'] ?? $entity->name,
            'entity_type' => $data['entity_type'] ?? $entity->entity_type,
            'width' => $data['width'] ?? $entity->width,
            'height' => $data['height'] ?? $entity->height,
            'x' => $data['x'] ?? $entity->x,
            'y' => $data['y'] ?? $entity->y,
            'detailable_type' => $data['detailable_type'] ?? $entity->detailable_type,
            'detailable_id' => $data['detailable_id'] ?? $entity->detailable_id,
        ];

        if (isset($data['page_id'])) {
            $page = Page::query()->findOrFail($data['page_id']);
            $this->assertGamePage($game, $page);
            $update['page_id'] = $page->id;
        }

        if (isset($data['layer_id'])) {
            $layer = Layer::query()->findOrFail($data['layer_id']);
            $this->assertGameLayer($game, $layer);
            $update['layer_id'] = $layer->id;
        }

        if (array_key_exists('file_id', $data)) {
            $update['file_id'] = $data['file_id'];
        }

        if (array_key_exists('block', $data) || array_key_exists('controller_user_ids', $data)) {
            $this->assertOwner($game, $user);
            if (array_key_exists('block', $data)) {
                $update['block'] = $data['block'];
            }
            if (array_key_exists('controller_user_ids', $data)) {
                $update['controller_user_ids'] = $data['controller_user_ids'];
            }
        }

        if (array_key_exists('token_settings', $data) || array_key_exists('light_source', $data) || array_key_exists('barrier', $data)) {
            $this->assertOwner($game, $user);
            $this->syncEntityDetails($entity, $update['entity_type'], $data);
            $entity->refresh();
            $update['detailable_type'] = $entity->detailable_type;
            $update['detailable_id'] = $entity->detailable_id;
        }

        $entity->update($update);

        $entity->load(['file', 'layer', 'detailable']);

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'entity.updated', [
            'entity' => $this->serializeEntity($entity),
        ]));

        return $entity;
    }

    public function deleteEntity(Game $game, Entity $entity, User $user): void
    {
        $this->assertParticipant($game, $user);
        $this->assertEntityGame($game, $entity);

        if (! $this->canEditEntity($game, $entity, $user)) {
            throw ValidationException::withMessages([
                'entity' => ['You do not have permission to delete this entity.'],
            ]);
        }

        $entity->loadMissing('detailable');
        $entityId = $entity->id;
        $detail = $entity->detailable;
        $drawing = $detail instanceof EntityDrawing ? $detail : null;
        $entity->delete();
        $drawing?->delete();
        if ($detail instanceof EntityTokenSetting || $detail instanceof EntityLightSource || $detail instanceof EntityBarrier) {
            $detail->delete();
        }

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'entity.deleted', [
            'entity_id' => $entityId,
        ]));
    }

    public function listChatMessages(Game $game, User $user): Collection
    {
        $this->assertParticipant($game, $user);

        return $game->chatMessages()->with('user.avatarFile')->latest()->limit(100)->get()
            ->reverse()->values()
            ->map(fn (GameChatMessage $message) => $this->serializeChatMessage($message));
    }

    public function createChatMessage(Game $game, User $user, string $message): GameChatMessage
    {
        $this->assertParticipant($game, $user);

        $chatMessage = $game->chatMessages()->create([
            'user_id' => $user->id,
            'type' => 'message',
            'message' => $message,
            'payload' => null,
        ])->load('user.avatarFile');

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'chat.message.created', [
            'message' => $this->serializeChatMessage($chatMessage),
        ]));

        return $chatMessage;
    }

    public function createDiceRollMessage(Game $game, User $user, string $formula): GameChatMessage
    {
        $this->assertParticipant($game, $user);

        $parsed = $this->diceParsingService->parse($formula);

        $chatMessage = $game->chatMessages()->create([
            'user_id' => $user->id,
            'type' => 'RUL_DICE',
            'message' => $formula,
            'payload' => $parsed,
        ])->load('user.avatarFile');

        $this->dispatchBroadcastSafely(new GameRealtimeEvent($game, 'chat.message.created', [
            'message' => $this->serializeChatMessage($chatMessage),
        ]));

        return $chatMessage;
    }

    public function serializeGameSummary(Game $game): array
    {
        return [
            'id' => $game->id,
            'name' => $game->name,
            'description' => $game->description,
            'key' => $game->key,
            'image' => $game->imageFile ? [
                'id' => $game->imageFile->id,
                'url' => route('files.show', $game->imageFile),
            ] : null,
            'owner' => [
                'id' => $game->ownerUser?->id,
                'login' => $game->ownerUser?->login,
            ],
            'active_page_id' => $game->active_page_id,
            'created_at' => $game->created_at,
            'updated_at' => $game->updated_at,
        ];
    }

    public function serializeGameDetail(Game $game): array
    {
        $summary = $this->serializeGameSummary($game);
        $summary['pages'] = $game->relationLoaded('pages')
            ? $game->pages->map(fn (Page $page) => $this->serializePage($page))
            : [];

        return $summary;
    }

    public function serializePage(Page $page): array
    {
        $page->loadMissing('settings');

        return [
            'id' => $page->id,
            'game_id' => $page->game_id,
            'name' => $page->name,
            'path' => $page->path,
            'settings' => $this->serializePageSettings($page->settings),
        ];
    }

    public function serializePageSettings(?PageSetting $settings): array
    {
        $settings ??= new PageSetting($this->defaultPageSettings());

        return [
            'canvas_width' => $settings->canvas_width ?? 3200,
            'canvas_height' => $settings->canvas_height ?? 2200,
            'grid_enabled' => $settings->grid_enabled ?? true,
            'grid_cell_size' => $settings->grid_cell_size ?? 100,
            'lighting_type' => $settings->lighting_type ?? 'off',
        ];
    }

    public function serializeLayer(Layer $layer): array
    {
        return [
            'id' => $layer->id,
            'game_id' => $layer->game_id,
            'name' => $layer->name,
            'type' => $layer->type,
            'level' => $layer->level,
            'visible' => $layer->visible,
            'interactive' => $layer->interactive,
        ];
    }

    public function serializeGameFile(GameFile $gameFile): array
    {
        $gameFile->loadMissing('file');

        return [
            'id' => $gameFile->id,
            'game_id' => $gameFile->game_id,
            'name' => $gameFile->name,
            'path' => $gameFile->path,
            'file' => $gameFile->file ? [
                'id' => $gameFile->file->id,
                'url' => route('files.show', $gameFile->file),
                'original_name' => $gameFile->file->original_name,
            ] : null,
            'file_id' => $gameFile->file_id,
        ];
    }

    public function serializeEntity(Entity $entity): array
    {
        $entity->loadMissing(['file', 'layer', 'detailable']);

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'entity_type' => $entity->entity_type ?? 'token',
            'page_id' => $entity->page_id,
            'layer_id' => $entity->layer_id,
            'width' => (float) $entity->width,
            'height' => (float) $entity->height,
            'x' => (float) $entity->x,
            'y' => (float) $entity->y,
            'file_id' => $entity->file_id,
            'block' => $entity->block,
            'controller_user_ids' => $entity->controller_user_ids ?? [],
            'detailable_type' => $entity->detailable_type,
            'detailable_id' => $entity->detailable_id,
            'shape' => $entity->detailable instanceof EntityDrawing ? [
                'id' => $entity->detailable->id,
                'kind' => $entity->detailable->kind,
                'color' => $entity->detailable->color,
                'commands' => $entity->detailable->commands ?? [],
            ] : null,
            'token_settings' => $entity->detailable instanceof EntityTokenSetting ? [
                'vision_enabled' => $entity->detailable->vision_enabled,
                'all_players' => $entity->detailable->all_players,
                'player_user_ids' => $entity->detailable->player_user_ids ?? [],
                'night_vision_enabled' => $entity->detailable->night_vision_enabled,
                'night_vision_range' => (float) ($entity->detailable->night_vision_range ?? 0),
                'light_enabled' => $entity->detailable->light_enabled,
                'light_radius' => (float) ($entity->detailable->light_radius ?? 0),
            ] : null,
            'light_source' => $entity->detailable instanceof EntityLightSource ? [
                'enabled' => $entity->detailable->enabled,
                'radius' => (float) ($entity->detailable->radius ?? 0),
            ] : null,
            'barrier' => $entity->detailable instanceof EntityBarrier ? [
                'kind' => $entity->detailable->kind,
                'is_open' => $entity->detailable->is_open,
                'x1' => (float) ($entity->detailable->x1 ?? 0),
                'y1' => (float) ($entity->detailable->y1 ?? 0),
                'x2' => (float) ($entity->detailable->x2 ?? 0),
                'y2' => (float) ($entity->detailable->y2 ?? 0),
            ] : null,
            'file' => $entity->file ? [
                'id' => $entity->file->id,
                'url' => route('files.show', $entity->file),
            ] : null,
        ];
    }

    public function serializeChatMessage(GameChatMessage $message): array
    {
        $message->loadMissing('user.avatarFile');

        return [
            'id' => $message->id,
            'game_id' => $message->game_id,
            'type' => $message->type,
            'message' => $message->message,
            'payload' => $message->payload,
            'created_at' => $message->created_at,
            'user' => [
                'id' => $message->user->id,
                'login' => $message->user->login,
                'avatar' => $message->user->avatarFile ? [
                    'id' => $message->user->avatarFile->id,
                    'url' => route('files.show', $message->user->avatarFile),
                ] : null,
            ],
        ];
    }

    public function assertOwner(Game $game, User $user): void
    {
        if (! $this->isOwner($game, $user)) {
            throw ValidationException::withMessages([
                'game' => ['Only the game owner may perform this action.'],
            ]);
        }
    }

    public function assertParticipant(Game $game, User $user): void
    {
        if ($this->isOwner($game, $user)) {
            return;
        }

        $isPlayer = GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isPlayer) {
            throw ValidationException::withMessages([
                'game' => ['Join the game before performing this action.'],
            ]);
        }
    }

    public function isOwner(Game $game, User $user): bool
    {
        return (int) $game->owner === (int) $user->id;
    }

    protected function assertGamePage(Game $game, Page $page): void
    {
        if ((int) $page->game_id !== (int) $game->id) {
            throw ValidationException::withMessages([
                'page' => ['Page does not belong to the selected game.'],
            ]);
        }
    }

    protected function assertGameLayer(Game $game, Layer $layer): void
    {
        if ((int) $layer->game_id !== (int) $game->id) {
            throw ValidationException::withMessages([
                'layer' => ['Layer does not belong to the selected game.'],
            ]);
        }
    }

    protected function assertGameFile(Game $game, GameFile $gameFile): void
    {
        if ((int) $gameFile->game_id !== (int) $game->id) {
            throw ValidationException::withMessages([
                'game_file' => ['Game file does not belong to the selected game.'],
            ]);
        }
    }

    protected function assertEntityGame(Game $game, Entity $entity): void
    {
        if ((int) $entity->page->game_id !== (int) $game->id) {
            throw ValidationException::withMessages([
                'entity' => ['Entity does not belong to the selected game.'],
            ]);
        }
    }

    protected function canEditEntity(Game $game, Entity $entity, User $user): bool
    {
        if ($this->isOwner($game, $user)) {
            return true;
        }

        if (! $entity->block) {
            return true;
        }

        return in_array($user->id, $entity->controller_user_ids ?? [], true);
    }

    protected function defaultPageSettings(): array
    {
        return [
            'canvas_width' => 3200,
            'canvas_height' => 2200,
            'grid_enabled' => true,
            'grid_cell_size' => 100,
            'lighting_type' => 'off',
        ];
    }

    protected function generateUniqueKey(): string
    {
        do {
            $key = Str::upper(Str::random(10));
        } while (Game::query()->where('key', $key)->exists());

        return $key;
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

    protected function syncEntityDetails(Entity $entity, string $entityType, array $data): void
    {
        $detail = $entity->detailable;

        if ($entityType === 'token') {
            if (! $detail instanceof EntityTokenSetting) {
                $detail?->delete();
                $detail = EntityTokenSetting::create();
                $entity->update([
                    'detailable_type' => EntityTokenSetting::class,
                    'detailable_id' => $detail->id,
                ]);
            }

            $detail->update([
                'vision_enabled' => data_get($data, 'token_settings.vision_enabled', $detail->vision_enabled),
                'all_players' => data_get($data, 'token_settings.all_players', $detail->all_players),
                'player_user_ids' => data_get($data, 'token_settings.player_user_ids', $detail->player_user_ids ?? []),
                'night_vision_enabled' => data_get($data, 'token_settings.night_vision_enabled', $detail->night_vision_enabled),
                'night_vision_range' => data_get($data, 'token_settings.night_vision_range', $detail->night_vision_range ?? 0),
                'light_enabled' => data_get($data, 'token_settings.light_enabled', $detail->light_enabled),
                'light_radius' => data_get($data, 'token_settings.light_radius', $detail->light_radius ?? 0),
            ]);

            return;
        }

        if ($entityType === 'light-source') {
            if (! $detail instanceof EntityLightSource) {
                $detail?->delete();
                $detail = EntityLightSource::create();
                $entity->update([
                    'detailable_type' => EntityLightSource::class,
                    'detailable_id' => $detail->id,
                ]);
            }

            $detail->update([
                'enabled' => data_get($data, 'light_source.enabled', $detail->enabled),
                'radius' => data_get($data, 'light_source.radius', $detail->radius ?? 0),
            ]);

            return;
        }

        if ($entityType === 'barrier') {
            if (! $detail instanceof EntityBarrier) {
                $detail?->delete();
                $detail = EntityBarrier::create();
                $entity->update([
                    'detailable_type' => EntityBarrier::class,
                    'detailable_id' => $detail->id,
                ]);
            }

            $detail->update([
                'kind' => data_get($data, 'barrier.kind', $detail->kind ?? 'wall'),
                'is_open' => data_get($data, 'barrier.is_open', $detail->is_open),
                'x1' => data_get($data, 'barrier.x1', $detail->x1 ?? $entity->x),
                'y1' => data_get($data, 'barrier.y1', $detail->y1 ?? $entity->y),
                'x2' => data_get($data, 'barrier.x2', $detail->x2 ?? ($entity->x + $entity->width)),
                'y2' => data_get($data, 'barrier.y2', $detail->y2 ?? ($entity->y + $entity->height)),
            ]);
        }
    }
}
