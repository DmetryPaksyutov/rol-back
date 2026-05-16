<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->userService->search(
                $data['search'] ?? null,
                $data['per_page'] ?? 15
            )
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->userService->summary($request->user()));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userService->profile($request->user()));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->userService->profile($user));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'login' => ['sometimes', 'string', 'max:255', 'unique:users,login,'.$user->id],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Профиль обновлён.',
            'data' => $this->userService->profile(
                $this->userService->updateProfile($user, $data)
            ),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'disk' => ['nullable', 'string'],
            'avatar' => ['required', 'image', 'max:10240'],
        ]);

        $user = $this->userService->updateAvatar(
            $request->user(),
            $request->file('avatar'),
            $data['disk'] ?? 'public'
        );

        return response()->json([
            'message' => 'Аватар обновлён.',
            'data' => $this->userService->profile($user),
        ]);
    }
}
