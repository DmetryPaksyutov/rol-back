<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        $email = Str::lower(trim($data['email']));
        $login = trim($data['login']);

        $this->ensureRegistrationUniqueness($email, $login);

        $user = DB::transaction(function () use ($data): User {
            return User::create([
                'name' => trim($data['login']),
                'login' => trim($data['login']),
                'email' => Str::lower(trim($data['email'])),
                'email_verified_at' => now(),
                'password' => Hash::make($data['password']),
            ]);
        });

        //$user->sendEmailVerificationNotification();

        return $user->fresh(['avatarFile']);
    }

    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверная почта или пароль.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Подтвердите адрес электронной почты перед входом.'],
            ]);
        }

        $token = auth('api')->attempt($credentials);

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => ['Не удалось выполнить вход.'],
            ]);
        }

        return $this->tokenPayload($token, $user->fresh(['avatarFile']));
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refresh(): array
    {
        $token = auth('api')->refresh();

        return $this->tokenPayload($token, auth('api')->user()->fresh(['avatarFile']));
    }

    public function verifyEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return true;
    }

    public function resendVerification(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function tokenPayload(string $token, User $user): array
    {
        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
                'avatar' => $user->avatarFile ? [
                    'id' => $user->avatarFile->id,
                    'url' => route('files.show', $user->avatarFile),
                ] : null,
            ],
        ];
    }

    protected function ensureRegistrationUniqueness(string $email, string $login): void
    {
        $errors = [];

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $errors['email'] = ['Пользователь с такой почтой уже существует.'];
        }

        if (User::query()->whereRaw('LOWER(login) = ?', [Str::lower($login)])->exists()) {
            $errors['login'] = ['Пользователь с таким логином уже существует.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
