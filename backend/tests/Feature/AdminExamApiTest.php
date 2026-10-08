<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminExamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_filter_student_attempts_without_exposing_private_answers_or_contact_details(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $test = $this->createTest($subject);
        $inProgress = $this->createAttempt($this->createStudent('Amina Student'), $test, 'in_progress');
        $submitted = $this->createAttempt($this->createStudent('Rafi Student'), $test, 'submitted');
        $this->createAttempt($this->createStudent('Mina Student'), $test, 'expired');
        $staff = $this->createStudent('Staff account', role: 'editor');
        $this->createAttempt($staff, $test, 'submitted');

        AttemptAnswer::query()->create([
            'attempt_id' => $inProgress->id,
            'sequence' => 1,
            'question_text' => 'Question snapshot',
            'options' => ['A', 'B'],
            'correct_option' => 1,
            'points' => 1,
            'selected_option' => 2,
            'answered_at' => now(),
        ]);

        $this->withToken($adminToken)->getJson('/api/admin/exams?status=in_progress&search=Nursing')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.in_progress', 1)
            ->assertJsonPath('meta.submitted', 1)
            ->assertJsonPath('meta.expired', 1)
            ->assertJsonPath('data.0.student.name', 'Amina Student')
            ->assertJsonPath('data.0.answered_count', 1)
            ->assertJsonPath('data.0.question_count', 1)
            ->assertJsonMissingPath('data.0.student.email')
            ->assertJsonMissingPath('data.0.answers');

        $this->withToken($adminToken)->getJson('/api/admin/exams?status=completed')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->withToken($adminToken)->getJson('/api/admin/exams?search=Rafi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.name', 'Rafi Student')
            ->assertJsonPath('data.0.status', 'submitted');

        $this->withToken($adminToken)->getJson('/api/admin/exams/'.$inProgress->id)
            ->assertOk()
            ->assertJsonPath('attempt.test.title', 'Nursing Practice')
            ->assertJsonPath('attempt.correct_count', 0)
            ->assertJsonMissingPath('attempt.answers')
            ->assertJsonMissingPath('attempt.student.email');
    }

    public function test_exam_monitoring_requires_staff_and_hides_attempts_for_non_students(): void
    {
        $studentToken = $this->createUserWithToken('student');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $test = $this->createTest($subject);
        $attempt = $this->createAttempt($this->createStudent('Student One'), $test, 'submitted');

        $this->getJson('/api/admin/exams')->assertUnauthorized();
        $this->withToken($studentToken)->getJson('/api/admin/exams')->assertForbidden();
        $this->withToken($studentToken)->getJson('/api/admin/exams/'.$attempt->id)->assertForbidden();
        $this->withToken($this->createUserWithToken('editor'))->getJson('/api/admin/exams')->assertOk();
        $this->withToken($this->createUserWithToken('admin'))->getJson('/api/admin/exams/999')->assertNotFound();
    }

    private function createUserWithToken(string $role): string
    {
        $user = User::factory()->create(['role' => $role]);
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Admin exam test token',
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

    private function createTest(Subject $subject): ModelTest
    {
        return ModelTest::query()->create([
            'title' => 'Nursing Practice',
            'code' => 'TST'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => 'published',
            'created_by' => $this->createStudent('Test Admin', role: 'admin')->id,
        ]);
    }

    private function createAttempt(User $student, ModelTest $test, string $status): ExamAttempt
    {
        return ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => $status,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(20),
            'finished_at' => $status === 'in_progress' ? null : now(),
            'duration_seconds' => $status === 'in_progress' ? 0 : 300,
            'score' => $status === 'in_progress' ? null : 1,
            'total_marks' => 1,
            'percentage' => $status === 'in_progress' ? null : 100,
            'correct_count' => $status === 'in_progress' ? 0 : 1,
            'incorrect_count' => 0,
            'unanswered_count' => 0,
            'passed' => $status === 'in_progress' ? null : true,
        ]);
    }
}
