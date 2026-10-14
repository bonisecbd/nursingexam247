<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'draft', 'published'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';

        $announcements = Announcement::query()
            ->withCount('notifications')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $announcements->getCollection()->map(
                fn (Announcement $announcement): array => $this->announcementData($announcement)
            )->all(),
            'meta' => [
                'total' => $announcements->total(),
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
            ],
        ]);
    }

    /**
     * Create an announcement; it is published (and fanned out) immediately
     * unless `publish` is false. Returns the announcement plus the number of
     * recipients actually delivered to (opt-outs excluded).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'target_audience' => ['sometimes', Rule::in(['all', 'students'])],
            'publish' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $result = $this->notifications->createAnnouncement($request->user(), $validated);

        return response()->json([
            'message' => $result['announcement']->status === 'published'
                ? 'Announcement published to '.$result['delivered'].' recipient(s).'
                : 'Announcement saved as a draft.',
            'announcement' => $this->announcementData($result['announcement']),
            'delivered' => $result['delivered'],
        ], 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    private function announcementData(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'target_audience' => $announcement->target_audience,
            'status' => $announcement->status,
            'published_at' => $announcement->published_at?->toISOString(),
            'expires_at' => $announcement->expires_at?->toISOString(),
            'created_by' => $announcement->created_by,
            'delivered_count' => (int) ($announcement->notifications_count ?? DB::table('notifications')->where('announcement_id', $announcement->id)->count()),
            'created_at' => $announcement->created_at?->toISOString(),
        ];
    }
}
