<?php

namespace App\Providers;

use App\Exceptions\FileInUseException;
use App\Models\File;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileService
{
    public function bulkUpload(array $uploadedFiles, string $disk): Collection
    {
        $this->ensureDiskExists($disk);

        $now = now();
        $directory = $now->format('Y/m/d');
        $storedFiles = [];
        $records = [];

        try {
            foreach ($uploadedFiles as $uploadedFile) {
                if (! $uploadedFile instanceof UploadedFile) {
                    throw new RuntimeException('Invalid uploaded file payload.');
                }

                $extension = $uploadedFile->getClientOriginalExtension();
                $name = Str::random(40).($extension ? '.'.$extension : '');
                $path = Storage::disk($disk)->putFileAs($directory, $uploadedFile, $name);

                if (! $path) {
                    throw new RuntimeException('Failed to store uploaded file.');
                }

                $storedFiles[] = [
                    'disk' => $disk,
                    'path' => $path,
                ];

                $records[] = [
                    'uuid' => (string) Str::uuid(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'name' => $name,
                    'hash' => hash_file('sha256', $uploadedFile->getRealPath()) ?: null,
                    'extension' => $extension ?: null,
                    'mime_type' => $uploadedFile->getClientMimeType(),
                    'size' => $uploadedFile->getSize() ?: 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($records): void {
                File::query()->insert($records);
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                if (Storage::disk($storedFile['disk'])->exists($storedFile['path'])) {
                    Storage::disk($storedFile['disk'])->delete($storedFile['path']);
                }
            }

            throw $exception;
        }

        return File::query()
            ->whereIn('uuid', array_column($records, 'uuid'))
            ->orderBy('id')
            ->get();
    }

    public function delete(File $file): void
    {
        $this->ensureDiskExists($file->disk);

        try {
            DB::transaction(function () use ($file): void {
                $disk = Storage::disk($file->disk);
                $path = $file->path;

                $file->delete();

                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('Failed to delete file from storage.');
                }
            });
        } catch (QueryException $exception) {
            throw new FileInUseException('Файл используется в других данных и не может быть удалён.', 0, $exception);
        }
    }

    public function ensureDiskExists(string $disk): void
    {
        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new RuntimeException("Disk [{$disk}] is not configured.");
        }
    }
}
