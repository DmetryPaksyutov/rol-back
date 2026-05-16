<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Providers\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'stream' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->notificationService->listForUser(
                $request->user(),
                $data['per_page'] ?? 30,
                $data['stream'] ?? null
            )
        );
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $notification);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $this->notificationService->serialize($notification),
        ]);
    }

    public function destroy(Request $request, UserNotification $notification): JsonResponse
    {
        $notificationId = $this->notificationService->delete($request->user(), $notification);

        return response()->json([
            'message' => 'Notification deleted.',
            'data' => [
                'id' => $notificationId,
            ],
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->notificationService->clear($request->user());

        return response()->json([
            'message' => 'Notifications cleared.',
        ]);
    }
}
