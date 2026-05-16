<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\GameChatController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameEntityController;
use App\Http\Controllers\GameFileController;
use App\Http\Controllers\GameLayerController;
use App\Http\Controllers\GamePageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.auth.verify-email');
});

Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');

Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/email/resend', [VerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
    });

    Route::get('/users/me/summary', [UserController::class, 'summary']);
    Route::get('/users/me', [UserController::class, 'me']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'clear']);

    Route::middleware('verified.api')->group(function () {
        Route::post('/files', [FileController::class, 'store']);
        Route::delete('/files/{file}', [FileController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/me', [UserController::class, 'update']);
        Route::post('/users/me/avatar', [UserController::class, 'updateAvatar']);

        Route::get('/friends', [FriendshipController::class, 'index']);
        Route::get('/friends/notifications', [FriendshipController::class, 'notifications']);
        Route::post('/friends/invitations/{user}', [FriendshipController::class, 'store']);
        Route::post('/friends/invitations/{friendship}/accept', [FriendshipController::class, 'accept']);
        Route::post('/friends/invitations/{friendship}/reject', [FriendshipController::class, 'reject']);

        Route::get('/games', [GameController::class, 'index']);
        Route::get('/games/my', [GameController::class, 'myGames']);
        Route::post('/games', [GameController::class, 'store']);
        Route::get('/games/{game}', [GameController::class, 'show']);
        Route::post('/games/{game}/invite', [GameController::class, 'invite']);
        Route::post('/games/{game}/join', [GameController::class, 'join']);
        Route::get('/games/{game}/players', [GameController::class, 'players']);

        Route::post('/games/{game}/pages', [GamePageController::class, 'store']);
        Route::patch('/games/{game}/pages/{page}', [GamePageController::class, 'update']);
        Route::delete('/games/{game}/pages/{page}', [GamePageController::class, 'destroy']);
        Route::post('/games/{game}/pages/{page}/activate', [GamePageController::class, 'activate']);
        Route::get('/games/{game}/pages/{page}/entities', [GamePageController::class, 'entities']);

        Route::post('/games/{game}/layers', [GameLayerController::class, 'store']);
        Route::patch('/games/{game}/layers/{layer}', [GameLayerController::class, 'update']);
        Route::delete('/games/{game}/layers/{layer}', [GameLayerController::class, 'destroy']);

        Route::get('/games/{game}/files', [GameFileController::class, 'index']);
        Route::post('/games/{game}/files', [GameFileController::class, 'store']);
        Route::patch('/games/{game}/files/{gameFile}', [GameFileController::class, 'update']);
        Route::delete('/games/{game}/files/{gameFile}', [GameFileController::class, 'destroy']);

        Route::post('/games/{game}/entities', [GameEntityController::class, 'store']);
        Route::patch('/games/{game}/entities/{entity}', [GameEntityController::class, 'update']);
        Route::delete('/games/{game}/entities/{entity}', [GameEntityController::class, 'destroy']);

        Route::get('/games/{game}/chat/messages', [GameChatController::class, 'index']);
        Route::post('/games/{game}/chat/messages', [GameChatController::class, 'store']);
        Route::post('/games/{game}/chat/dice', [GameChatController::class, 'rollDice']);
    });
});
