<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification hash.');
        }

        $verified = $this->authService->verifyEmail($user);

        return response()->json([
            'message' => $verified
                ? 'Электронная почта успешно подтверждена.'
                : 'Электронная почта уже подтверждена.',
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authService->resendVerification($user);

        return response()->json([
            'message' => $user->hasVerifiedEmail()
                ? 'Почта уже подтверждена.'
                : 'Письмо с подтверждением отправлено повторно.',
        ]);
    }
}
