<?php

namespace App\Http\Controllers;

use App\Exceptions\FileInUseException;
use App\Models\File;
use App\Providers\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'disk' => ['required', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file'],
        ]);

        $files = $this->fileService->bulkUpload($request->file('files', []), $data['disk']);

        return response()->json([
            'data' => $files,
        ], 201);
    }

    public function show(File $file): StreamedResponse
    {
        $disk = Storage::disk($file->disk);
        $stream = $disk->readStream($file->path);

        abort_unless($stream !== false, 404, 'File not found.');

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => 'inline; filename="'.$file->original_name.'"',
        ]);
    }

    public function destroy(File $file): JsonResponse
    {
        try {
            $this->fileService->delete($file);
        } catch (FileInUseException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Файл удалён.',
        ]);
    }
}
