<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminResultApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_filter_finalized_results_and_read_aggregate_performance(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $editorToken = $this->createUserWithToken('editor');
        $nursing = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
        $nursingTest = $this->createTest($nursing, 'Nursing Practice');
        $englishTest = $this->createTest($english, 'English Practice');
        $passedAttempt = $this->createAttempt($this->createStudent('Amina Student'), $nursingTest, 'submitted', 80, true);
        $failedAttempt = $this->createAttempt($this->createStudent('Rafi Student'), $nursingTest, 'expired', 40, false);
        $this->createAttempt($this->createStudent('Mina Student'), $englishTest, 'submitted', 60, true);
        $this->createAttempt($this->createStudent('Active Student'), $englishTest, 'in_progress', null, null);
        $this->createAttempt($this->createStudent('Staff User', 'editor'), $englishTest, 'submitted', 99, true);

        $this->withToken($adminToken)->getJson('/api/admin/results?status=passed&subject_id='.$nursing->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.attempt_id', $passedAttempt->id)
            ->assertJsonPath('data.0.student.name', 'Amina Student')
            ->assertJsonPath('data.0.test.subject', 'Nursing')
            ->assertJsonMissingPath('data.0.student.email')
            ->assertJsonMissingPath('data.0.answers')
            ->assertJsonMissingPath('data.0.correct_option')
            ->assertJsonPath('analytics.completed_attempts', 3)
            ->assertJsonPath('analytics.average_percentage', 60)
            ->assertJsonPath('analytics.pass_rate', 66.67)
            ->assertJsonPath('analytics.subjects.0.name', 'Nursing')
            ->assertJsonPath('analytics.subjects.0.attempts', 2)
            ->assertJsonPath('analytics.subjects.0.average_percentage', 60)
            ->assertJsonPath('analytics.tests.0.title', 'Nursing Practice');

        $this->withToken($editorToken)->getJson('/api/admin/results?search=Rafi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attempt_id', $failedAttempt->id)
            ->assertJsonPath('data.0.passed', false);

        $this->withToken($adminToken)->getJson('/api/admin/results?status=in_progress')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_result_analytics_are_empty_when_no_student_has_finished_an_attempt(): void
    {
        $adminToken = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->getJson('/api/admin/results')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('analytics.completed_attempts', 0)
            ->assertJsonPath('analytics.average_percentage', null)
            ->assertJsonPath('analytics.pass_rate', null)
            ->assertJsonCount(0, 'analytics.subjects')
            ->assertJsonCount(0, 'analytics.tests');
    }

    public function test_result_monitoring_is_restricted_to_admins_and_editors(): void
    {
        $studentToken = $this->createUserWithToken('student');

        $this->getJson('/api/admin/results')->assertUnauthorized();
        $this->withToken($studentToken)->getJson('/api/admin/results')->assertForbidden();
    }

    private function createUserWithToken(string $role): string
    {
        $user = User::factory()->create(['role' => $role]);
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Admin results test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }

    private function createStudent(string $name, string $role = 'student'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role,
            'email' => Str::lower(Str::random(12)).'@example.com',
        ]);
    }

    private function createTest(Subject $subject, string $title): ModelTest
    {
        return ModelTest::query()->create([
            'title' => $title,
            'code' => 'TST'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 10,
            'passing_score' => 5,
            'status' => 'published',
            'created_by' => $this->createStudent('Test Admin', 'admin')->id,
        ]);
    }

    private function createAttempt(
        User $student,
        ModelTest $test,
        string $status,
        ?float $percentage,
        ?bool $passed,
    ): ExamAttempt {
        return ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => $status,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(20),
            'finished_at' => $status === 'in_progress' ? null : now(),
            'duration_seconds' => $status === 'in_progress' ? 0 : 300,
            'score' => $percentage === null ? null : $percentage / 10,
            'total_marks' => 10,
            'percentage' => $percentage,
            'correct_count' => $passed === true ? 8 : 4,
            'incorrect_count' => $passed === false ? 6 : 2,
            'unanswered_count' => 0,
            'passed' => $passed,
        ]);
    }
}
