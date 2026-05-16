<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function search(?string $search, int $perPage = 15)
    {
        return User::query()
            ->with('avatarFile')
            ->when($search, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('login', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('login')
            ->paginate($perPage);
    }

    public function summary(User $user): array
    {
        $user->loadMissing('avatarFile');

        return [
            'id' => $user->id,
            'login' => $user->login,
            'email' => $user->email,
            'avatar' => $user->avatarFile ? [
                'id' => $user->avatarFile->id,
                'url' => route('files.show', $user->avatarFile),
            ] : null,
        ];
    }

    public function profile(User $user): array
    {
        $user->loadMissing('avatarFile');

        return [
            'id' => $user->id,
            'login' => $user->login,
            'email' => $user->email,
            'description' => $user->description,
            'avatar' => $user->avatarFile ? [
                'id' => $user->avatarFile->id,
                'url' => route('files.show', $user->avatarFile),
            ] : null,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill([
            'name' => $data['login'] ?? $user->name,
            'login' => $data['login'] ?? $user->login,
            'email' => $data['email'] ?? $user->email,
            'description' => $data['description'] ?? $user->description,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user->fresh(['avatarFile']);
    }

    public function updateAvatar(User $user, UploadedFile $uploadedFile, string $disk): User
    {
        $newFile = $this->fileService->bulkUpload([$uploadedFile], $disk)->first();
        $oldAvatar = $user->avatarFile;

        DB::transaction(function () use ($user, $newFile): void {
            $user->avatar_file_id = $newFile->id;
            $user->save();
        });

        if ($oldAvatar && $oldAvatar->id !== $newFile->id) {
            try {
                $this->fileService->delete($oldAvatar);
            } catch (\Throwable) {
                // Ignore cleanup issues for the previous avatar.
            }
        }

        return $user->fresh(['avatarFile']);
    }
}
