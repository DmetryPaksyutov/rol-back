<?php

namespace App\Http\Controllers;

use App\Providers\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'login' => ['required', 'string', 'max:255', 'unique:users,login'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $this->authService->register($data);

        return response()->json([
            'message' => 'Пользователь зарегистрирован. Проверьте почту для подтверждения адреса.',
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return response()->json($this->authService->login($data));
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Вы вышли из системы.',
        ]);
    }

    public function refresh(): JsonResponse
    {
        return response()->json($this->authService->refresh());
    }
}
