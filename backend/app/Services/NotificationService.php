<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * In-app notifications with read tracking, category opt-outs, deduplication,
 * and admin announcements.
 *
 * Delivery is in-app only; the `channel` column exists so email/SMS/push can
 * be added later without schema change. Transient delivery retries are not
 * implemented yet — the dedup key guarantees that a retried `notify()` call
 * never creates a second user-visible row.
 *
 * Content policy: callers must never pass passwords, OTP values, bearer
 * tokens, payment credentials, or full private profiles into title/body/data.
 */
class NotificationService
{
    /**
     * Preference category per notification type. Types not listed here
     * (e.g. account/security notices) bypass opt-outs by policy.
     *
     * @var array<string, string>
     */
    private const TYPE_CATEGORIES = [
        'announcement' => 'announcements',
        'reward' => 'rewards',
        'badge' => 'rewards',
        'result' => 'results',
        'offer' => 'offers',
        'reminder' => 'reminders',
    ];

    /**
     * Create (or idempotently replay) an in-app notification.
     *
     * Other modules (gamification rewards/badges, exam results, offers,
     * referrals, ...) call this; it is the single integration point. When
     * $dedupKey is provided and a row with the same (user, key) exists, the
     * existing notification is returned unchanged.
     *
     * Returns null when the user opted out of the notification's category.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $dedupKey = null,
        ?int $announcementId = null,
    ): ?Notification {
        if (! $this->categoryAllowed($userId, $type)) {
            return null;
        }

        $attributes = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
            'channel' => 'in_app',
            'status' => 'unread',
            'dedup_key' => $dedupKey,
            'announcement_id' => $announcementId,
        ];

        try {
            return Notification::query()->create($attributes);
        } catch (QueryException $exception) {
            // Unique (user_id, dedup_key) collision: a concurrent or retried
            // delivery already created this notification. Report that row.
            if ($dedupKey !== null) {
                $existing = Notification::query()
                    ->where('user_id', $userId)
                    ->where('dedup_key', $dedupKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    /**
     * Caller-scoped, newest-first, paginated list.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(User $user, int $perPage, ?string $status = null): array
    {
        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => $notifications->getCollection()->map(
                fn (Notification $notification): array => $this->notificationData($notification)
            )->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->unreadCount($user),
            ],
        ];
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('status', 'unread')
            ->count();
    }

    /**
     * Ownership-scoped: another user's notification is a 404, never a 403
     * existence probe. Marking an already-read notification is idempotent.
     */
    public function markRead(User $user, int $notificationId): array
    {
        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->findOrFail($notificationId);

        if ($notification->status !== 'read') {
            $notification->forceFill([
                'status' => 'read',
                'read_at' => now(),
            ])->save();
        }

        return $this->notificationData($notification);
    }

    /**
     * Mark all of the caller's unread notifications read.
     *
     * @return int number of notifications marked read
     */
    public function markAllRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Preferences are created lazily with all categories enabled.
     *
     * @return array<string, bool>
     */
    public function preferences(User $user): array
    {
        $preference = $this->preferenceRow($user->id);

        return $this->preferenceData($preference);
    }

    /**
     * @param  array<string, bool>  $categories
     * @return array<string, bool>
     */
    public function updatePreferences(User $user, array $categories): array
    {
        $preference = $this->preferenceRow($user->id);
        $preference->forceFill($categories)->save();

        return $this->preferenceData($preference->refresh());
    }

    /**
     * Create and (by default) immediately publish an announcement, fanning
     * out one notification per eligible recipient. Author/audit data is kept
     * on the announcement row.
     *
     * @param  array{title: string, body: string, target_audience?: string, publish?: bool}  $validated
     * @return array{announcement: Announcement, delivered: int}
     */
    public function createAnnouncement(User $admin, array $validated): array
    {
        return DB::transaction(function () use ($admin, $validated): array {
            $publish = (bool) ($validated['publish'] ?? true);

            $announcement = Announcement::query()->create([
                'created_by' => $admin->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_audience' => $validated['target_audience'] ?? 'all',
                'status' => $publish ? 'published' : 'draft',
                'published_at' => $publish ? now() : null,
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            $delivered = $publish ? $this->broadcast($announcement) : 0;

            return ['announcement' => $announcement, 'delivered' => $delivered];
        });
    }

    /**
     * Fan a published announcement out to its target audience. Each user gets
     * at most one notification per announcement via the dedup key, so a
     * re-broadcast never duplicates user-visible rows.
     *
     * @return int number of notifications delivered
     */
    public function broadcast(Announcement $announcement): int
    {
        $query = User::query()
            ->where('is_active', true)
            ->when(
                $announcement->target_audience === 'students',
                fn ($query) => $query->where('role', 'student'),
            );

        $delivered = 0;
        $query->select('id')->chunkById(200, function ($users) use ($announcement, &$delivered): void {
            foreach ($users as $user) {
                $notification = $this->notify(
                    (int) $user->id,
                    'announcement',
                    $announcement->title,
                    $announcement->body,
                    ['announcement_id' => $announcement->id],
                    'announcement:'.$announcement->id,
                    $announcement->id,
                );

                if ($notification !== null && $notification->wasRecentlyCreated) {
                    $delivered++;
                }
            }
        });

        return $delivered;
    }

    private function categoryAllowed(int $userId, string $type): bool
    {
        $category = self::TYPE_CATEGORIES[$type] ?? null;
        if ($category === null) {
            // Account/security notices bypass opt-outs by documented policy.
            return true;
        }

        $preference = $this->preferenceRow($userId);
        if (! $preference->in_app_enabled) {
            return false;
        }

        return (bool) $preference->{$category};
    }

    private function preferenceRow(int $userId): NotificationPreference
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $userId)
            ->first();

        if ($preference) {
            return $preference;
        }

        try {
            return NotificationPreference::query()->create([
                'user_id' => $userId,
                'in_app_enabled' => true,
                'announcements' => true,
                'rewards' => true,
                'results' => true,
                'offers' => true,
                'reminders' => true,
            ]);
        } catch (QueryException) {
            // A concurrent request created the row first (unique user_id).
            return NotificationPreference::query()->where('user_id', $userId)->firstOrFail();
        }
    }

    /**
     * @return array<string, bool>
     */
    private function preferenceData(NotificationPreference $preference): array
    {
        return [
            'in_app_enabled' => (bool) $preference->in_app_enabled,
            'announcements' => (bool) $preference->announcements,
            'rewards' => (bool) $preference->rewards,
            'results' => (bool) $preference->results,
            'offers' => (bool) $preference->offers,
            'reminders' => (bool) $preference->reminders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationData(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
            'channel' => $notification->channel,
            'status' => $notification->status,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
