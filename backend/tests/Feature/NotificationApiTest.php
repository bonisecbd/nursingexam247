<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->patchJson('/api/notifications/1/read')->assertUnauthorized();
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
        $this->patchJson('/api/notification-preferences', ['offers' => false])->assertUnauthorized();
        $this->getJson('/api/admin/announcements')->assertUnauthorized();
        $this->postJson('/api/admin/announcements', ['title' => 'Hi', 'body' => 'Body'])->assertUnauthorized();
    }

    public function test_notifications_list_is_paginated_owner_scoped_and_newest_first(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [$other, $otherToken] = $this->createUserWithToken();

        $service = app(NotificationService::class);
        $service->notify($user->id, 'reminder', 'Older reminder', 'Study a bit today.');
        $service->notify($user->id, 'reward', 'Points earned', 'You earned 5 points.', ['points' => 5]);
        $service->notify($user->id, 'result', 'Result ready', 'Your test result is ready.');
        // Another student's notifications must never leak into this feed.
        $service->notify($other->id, 'offer', 'Secret offer', 'Not for you.');

        $page = $this->withToken($token)->getJson('/api/notifications?per_page=2');
        $page->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonPath('data.0.type', 'result')
            ->assertJsonPath('data.1.type', 'reward')
            ->assertJsonPath('data.0.status', 'unread')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonMissingPath('data.2');

        $this->withToken($token)->getJson('/api/notifications?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
        $this->withToken($token)->getJson('/api/notifications?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
        $this->withToken($token)->getJson('/api/notifications?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        // The other student sees exactly one notification: their own.
        $this->withToken($otherToken)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'offer');
    }

    public function test_unread_count_reports_only_the_callers_unread_notifications(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [$other, $otherToken] = $this->createUserWithToken();

        $service = app(NotificationService::class);
        $service->notify($user->id, 'reminder', 'One');
        $service->notify($user->id, 'reminder', 'Two');
        $service->notify($user->id, 'result', 'Three');
        $service->notify($other->id, 'offer', 'Foreign');

        $this->withToken($token)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 3);

        $this->withToken($otherToken)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_mark_single_notification_read_and_idempotent_remark(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $id = app(NotificationService::class)->notify($user->id, 'reward', 'Badge earned')->id;

        $this->withToken($token)->patchJson("/api/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read.')
            ->assertJsonPath('notification.id', $id)
            ->assertJsonPath('notification.status', 'read')
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($this->withToken($token)->getJson('/api/notifications')->json('data.0.read_at'));

        // Marking an already-read notification again is a no-op, not an error.
        $this->withToken($token)->patchJson("/api/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('notification.status', 'read');
    }

    public function test_mark_read_is_owner_scoped_foreign_notification_is_404(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();

        $id = app(NotificationService::class)->notify($user->id, 'offer', 'Private offer')->id;

        // Another student cannot read, mark, or even confirm the existence of it.
        $this->withToken($otherToken)->patchJson("/api/notifications/{$id}/read")->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $id, 'status' => 'unread']);

        $this->withToken($token)->patchJson('/api/notifications/999999/read')->assertNotFound();
    }

    public function test_mark_all_read_marks_every_unread_notification_of_the_caller(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [$other, $otherToken] = $this->createUserWithToken();

        $service = app(NotificationService::class);
        $service->notify($user->id, 'reminder', 'One');
        $service->notify($user->id, 'reward', 'Two');
        $service->notify($other->id, 'offer', 'Foreign');

        $this->withToken($token)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked_read', 2)
            ->assertJsonPath('unread_count', 0);

        // Only the caller's rows flipped to read.
        $this->assertSame(2, DB::table('notifications')->where('status', 'read')->count());
        $this->assertDatabaseHas('notifications', ['title' => 'Foreign', 'status' => 'unread']);

        // A second read-all finds nothing left to mark.
        $this->withToken($token)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked_read', 0);

        $this->withToken($otherToken)->getJson('/api/notifications/unread-count')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_status_filter_returns_unread_or_read_only(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $service = app(NotificationService::class);

        $readId = $service->notify($user->id, 'result', 'Old result')->id;
        $service->notify($user->id, 'reward', 'New reward');
        $this->withToken($token)->patchJson("/api/notifications/{$readId}/read")->assertOk();

        $this->withToken($token)->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'New reward');

        $this->withToken($token)->getJson('/api/notifications?status=read')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Old result')
            ->assertJsonPath('data.0.status', 'read');
    }

    public function test_admin_announcement_broadcasts_to_active_users_with_audit_data(): void
    {
        [$admin, $adminToken] = $this->createUserWithToken('admin');
        [$student] = $this->createUserWithToken();
        $this->createUserWithToken('editor');
        $inactive = User::factory()->create(['role' => 'student', 'is_active' => false]);

        $response = $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Maintenance tonight',
            'body' => 'The app will be unavailable from 22:00 to 23:00 UTC.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('announcement.status', 'published')
            ->assertJsonPath('announcement.target_audience', 'all')
            ->assertJsonPath('announcement.created_by', $admin->id)
            ->assertJsonPath('announcement.published_at', fn ($value) => $value !== null)
            ->assertJsonPath('delivered', 3); // admin + student + editor; inactive excluded

        $this->assertDatabaseCount('notifications', 3);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'announcement',
            'title' => 'Maintenance tonight',
            'status' => 'unread',
            'announcement_id' => $response->json('announcement.id'),
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $inactive->id]);

        // The student sees it in their feed with the structured payload.
        $this->withToken($this->tokenFor($student))->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'announcement')
            ->assertJsonPath('data.0.data.announcement_id', $response->json('announcement.id'));

        // Re-publishing with the same announcement cannot double-deliver;
        // admin listing shows the announcement with its delivered count.
        $this->withToken($adminToken)->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.delivered_count', 3)
            ->assertJsonPath('data.0.title', 'Maintenance tonight');
    }

    public function test_announcement_can_target_students_only(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [$student] = $this->createUserWithToken();
        $this->createUserWithToken('editor');

        $response = $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Students only',
            'body' => 'New practice set available.',
            'target_audience' => 'students',
        ]);

        $response->assertCreated()->assertJsonPath('delivered', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->id, 'title' => 'Students only']);
    }

    public function test_announcements_require_the_admin_role(): void
    {
        [, $studentToken] = $this->createUserWithToken();
        [, $editorToken] = $this->createUserWithToken('editor');

        $payload = ['title' => 'Hello', 'body' => 'Announcement body.'];
        $this->withToken($studentToken)->postJson('/api/admin/announcements', $payload)->assertForbidden();
        $this->withToken($editorToken)->postJson('/api/admin/announcements', $payload)->assertForbidden();
        $this->withToken($studentToken)->getJson('/api/admin/announcements')->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_announcement_validation_rejects_missing_or_invalid_fields(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'ab',
            'body' => 'Valid body length.',
        ])->assertUnprocessable()->assertJsonValidationErrors('title');

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Valid title',
            'body' => 'Valid body length.',
            'target_audience' => 'everyone',
        ])->assertUnprocessable()->assertJsonValidationErrors('target_audience');

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Valid title',
            'body' => 'Valid body length.',
            'publish' => 'yes-please',
        ])->assertUnprocessable()->assertJsonValidationErrors('publish');

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Valid title',
            'body' => 'Valid body length.',
            'expires_at' => now()->subDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_draft_announcement_creates_no_notifications(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        $this->createUserWithToken();

        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Draft notice',
            'body' => 'Not published yet.',
            'publish' => false,
        ])->assertCreated()
            ->assertJsonPath('announcement.status', 'draft')
            ->assertJsonPath('announcement.published_at', null)
            ->assertJsonPath('delivered', 0);

        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('notifications', 0);

        $this->withToken($adminToken)->getJson('/api/admin/announcements?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->withToken($adminToken)->getJson('/api/admin/announcements?status=published')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_notification_preferences_default_update_and_validation(): void
    {
        [$user, $token] = $this->createUserWithToken();

        // Defaults: everything enabled, created lazily on first read.
        $this->withToken($token)->getJson('/api/notification-preferences')
            ->assertOk()
            ->assertJsonPath('preferences.in_app_enabled', true)
            ->assertJsonPath('preferences.announcements', true)
            ->assertJsonPath('preferences.rewards', true)
            ->assertJsonPath('preferences.results', true)
            ->assertJsonPath('preferences.offers', true)
            ->assertJsonPath('preferences.reminders', true);

        $this->withToken($token)->patchJson('/api/notification-preferences', ['offers' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Notification preferences updated.')
            ->assertJsonPath('preferences.offers', false)
            ->assertJsonPath('preferences.rewards', true);

        // Partial update keeps other categories untouched.
        $this->withToken($token)->patchJson('/api/notification-preferences', ['rewards' => false])
            ->assertOk()
            ->assertJsonPath('preferences.rewards', false)
            ->assertJsonPath('preferences.offers', false)
            ->assertJsonPath('preferences.reminders', true);

        $this->withToken($token)->patchJson('/api/notification-preferences', ['rewards' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rewards');
        $this->withToken($token)->patchJson('/api/notification-preferences', ['unknown_category' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unknown_category');

        // The updated preference row persisted.
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'offers' => false,
            'rewards' => false,
        ]);
    }

    public function test_category_opt_out_suppresses_delivery_but_critical_types_bypass(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [$optedOut, $optedOutToken] = $this->createUserWithToken();
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($optedOutToken)->patchJson('/api/notification-preferences', [
            'announcements' => false,
            'offers' => false,
        ])->assertOk()->assertJsonPath('preferences.announcements', false);

        $service = app(NotificationService::class);

        // Opted out: announcement and offer notifications are suppressed.
        $this->assertNull($service->notify($optedOut->id, 'announcement', 'Gone', 'Suppressed.'));
        $this->assertNull($service->notify($optedOut->id, 'offer', 'Deal', 'Suppressed.'));
        // Critical account/security types bypass opt-outs by policy.
        $this->assertNotNull($service->notify($optedOut->id, 'account', 'Password changed', 'If this was not you, reset it.'));

        // Broadcast respects the opt-out too.
        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Campus update',
            'body' => 'Details inside.',
        ])->assertCreated()->assertJsonPath('delivered', 2); // user + admin, not optedOut

        $this->assertDatabaseMissing('notifications', ['user_id' => $optedOut->id, 'type' => 'announcement']);
        $this->assertDatabaseHas('notifications', ['user_id' => $optedOut->id, 'type' => 'account']);

        // Opted-in user still receives the broadcast.
        $this->withToken($token)->getJson('/api/notifications/unread-count')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_notify_deduplicates_via_dedup_key_and_replays_existing_row(): void
    {
        [$user] = $this->createUserWithToken();
        $service = app(NotificationService::class);

        $first = $service->notify($user->id, 'result', 'Result ready', null, ['attempt_id' => 1], 'result:attempt:1');
        $second = $service->notify($user->id, 'result', 'Result ready', null, ['attempt_id' => 1], 'result:attempt:1');

        $this->assertNotNull($first);
        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('notifications', 1);

        // Without a dedup key, distinct rows are allowed.
        $service->notify($user->id, 'reminder', 'Same title');
        $service->notify($user->id, 'reminder', 'Same title');
        $this->assertDatabaseCount('notifications', 3);
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        return [$user, $this->tokenFor($user)];
    }

    private function tokenFor(User $user): string
    {
        $plainTextToken = 'notification-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Notification test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainTextToken;
    }
}
