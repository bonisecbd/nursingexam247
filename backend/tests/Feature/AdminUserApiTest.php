<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_paginated_students(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        User::factory()->create([
            'name' => 'Active Student',
            'email' => 'active@example.com',
            'role' => 'student',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Blocked Student',
            'email' => 'blocked@example.com',
            'role' => 'student',
            'is_active' => false,
        ]);
        User::factory()->create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'role' => 'editor',
        ]);

        $this->withToken($adminToken)->getJson('/api/admin/users?status=active&search=active&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.active', 1)
            ->assertJsonPath('meta.blocked', 1)
            ->assertJsonPath('data.0.name', 'Active Student')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonMissingPath('data.0.password');

        $this->withToken($adminToken)->getJson('/api/admin/users?status=blocked')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Blocked Student');
    }

    public function test_admin_can_view_student_profile_and_recent_exam_activity(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $student = User::factory()->create([
            'role' => 'student',
            'phone' => '01700000000',
            'address' => 'Dhaka',
        ]);
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $test = ModelTest::query()->create([
            'title' => 'Practice test',
            'code' => 'TST'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
        ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(25),
            'finished_at' => now(),
            'score' => 8,
            'total_marks' => 10,
            'percentage' => 80,
            'duration_seconds' => 300,
            'correct_count' => 8,
            'incorrect_count' => 1,
            'unanswered_count' => 1,
        ]);

        $this->withToken($adminToken)->getJson("/api/admin/users/{$student->id}")
            ->assertOk()
            ->assertJsonPath('user.email', $student->email)
            ->assertJsonPath('user.address', 'Dhaka')
            ->assertJsonPath('activity.total_attempts', 1)
            ->assertJsonPath('activity.completed_attempts', 1)
            ->assertJsonPath('activity.average_score', 80)
            ->assertJsonPath('activity.recent_attempts.0.test.title', 'Practice test')
            ->assertJsonMissingPath('user.password');
    }

    public function test_blocking_a_student_revokes_all_api_tokens_and_prevents_access(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $studentToken = $this->createUserWithToken('student');
        $student = User::query()->where('role', 'student')->firstOrFail();
        $student->apiTokens()->create([
            'name' => 'Second device',
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($adminToken)->patchJson("/api/admin/users/{$student->id}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('user.is_active', false);

        $revokedTokens = ApiToken::query()->where('user_id', $student->id)->get();
        $this->assertCount(2, $revokedTokens);
        $this->assertTrue($revokedTokens->every(fn (ApiToken $token): bool => $token->expires_at->isPast()));
        $this->withToken($studentToken)->getJson('/api/auth/me')->assertUnauthorized();

        $this->withToken($adminToken)->patchJson("/api/admin/users/{$student->id}/status", [
            'is_active' => true,
        ])->assertOk()->assertJsonPath('user.is_active', true);

        $this->withToken($studentToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_user_management_is_admin_only_and_cannot_change_staff_accounts(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $editorToken = $this->createUserWithToken('editor');
        $staff = User::factory()->create(['role' => 'editor']);
        $student = User::factory()->create(['role' => 'student']);

        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->withToken($editorToken)->getJson('/api/admin/users')->assertForbidden();
        $this->withToken($editorToken)->getJson("/api/admin/users/{$student->id}")->assertForbidden();
        $this->withToken($adminToken)->getJson("/api/admin/users/{$staff->id}")->assertNotFound();
        $this->withToken($adminToken)->patchJson("/api/admin/users/{$staff->id}/status", [
            'is_active' => false,
        ])->assertNotFound();
        $this->withToken($adminToken)->patchJson("/api/admin/users/{$student->id}/status", [
            'is_active' => 'invalid',
        ])->assertUnprocessable();
    }

    private function createUserWithToken(string $role): string
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
        $plainTextToken = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'User management test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainTextToken;
    }
}
