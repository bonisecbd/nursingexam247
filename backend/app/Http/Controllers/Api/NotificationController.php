<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(['unread', 'read'])],
        ]);

        return response()->json($this->notifications->list(
            $request->user(),
            (int) ($validated['per_page'] ?? 20),
            $validated['status'] ?? null,
        ));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $this->notifications->markRead($request->user(), $notification),
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $marked = $this->notifications->markAllRead($request->user());

        return response()->json([
            'message' => $marked === 0
                ? 'You have no unread notifications.'
                : 'All notifications marked as read.',
            'marked_read' => $marked,
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->notifications->preferences($request->user()),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'in_app_enabled' => ['sometimes', 'boolean'],
            'announcements' => ['sometimes', 'boolean'],
            'rewards' => ['sometimes', 'boolean'],
            'results' => ['sometimes', 'boolean'],
            'offers' => ['sometimes', 'boolean'],
            'reminders' => ['sometimes', 'boolean'],
        ]);

        // Validation rules ignore unknown keys; reject them so a typo never
        // silently stores a meaningless attribute.
        $unknown = array_diff(array_keys($request->all()), array_keys($validated));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                array_values($unknown)[0] => ['Unknown notification preference field.'],
            ]);
        }

        return response()->json([
            'message' => 'Notification preferences updated.',
            'preferences' => $this->notifications->updatePreferences($request->user(), $validated),
        ]);
    }
}
