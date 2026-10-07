<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_dashboard_metrics_and_seven_day_exam_activity(): void
    {
        $adminToken = $this->createUserWithToken('admin', 'admin@example.com');
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Nursing',
            'code' => 'NUR',
        ]);
        $test = ModelTest::query()->create([
            'title' => 'Published exam',
            'code' => 'PUB'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
        ModelTest::query()->create([
            'title' => 'Draft exam',
            'code' => 'DRF'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => 'draft',
            'created_by' => User::query()->where('email', 'admin@example.com')->value('id'),
        ]);
        ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(25),
            'finished_at' => now(),
            'score' => 1,
            'total_marks' => 1,
            'percentage' => 100,
            'duration_seconds' => 300,
        ]);

        $this->withToken($adminToken)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.students', 2)
            ->assertJsonPath('metrics.active_students', 1)
            ->assertJsonPath('metrics.questions', 0)
            ->assertJsonPath('metrics.tests', 2)
            ->assertJsonPath('metrics.published_tests', 1)
            ->assertJsonPath('metrics.draft_tests', 1)
            ->assertJsonPath('metrics.active_subjects', 1)
            ->assertJsonPath('metrics.attempts_today', 1)
            ->assertJsonPath('metrics.finished_attempts', 1)
            ->assertJsonPath('metrics.average_score', 100)
            ->assertJsonPath('metrics.completion_rate', 100)
            ->assertJsonCount(7, 'exam_activity')
            ->assertJsonPath('unavailable_metrics.0', 'revenue');
    }

    public function test_dashboard_metrics_are_forbidden_to_students_and_unauthenticated_users(): void
    {
        $studentToken = $this->createUserWithToken('student', 'student@example.com');

        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->withToken($studentToken)->getJson('/api/admin/dashboard')->assertForbidden();
    }

    private function createUserWithToken(string $role, string $email): string
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => $role,
            'is_active' => true,
        ]);
        $plainTextToken = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Dashboard test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainTextToken;
    }
}
