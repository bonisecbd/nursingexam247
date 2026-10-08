<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnlockStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_test_stays_locked_until_the_previous_published_test_is_completed(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $first = $this->createTest($subject, $author, 'Nursing Model Test 01');
        $second = $this->createTest($subject, $author, 'Nursing Model Test 02');
        $draft = $this->createTest($subject, $author, 'Unpublished Draft', 'draft');
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);

        $this->getJson('/api/tests/'.$first->id.'/unlock-status')->assertUnauthorized();

        // The first test in the published sequence is always open.
        $this->withToken($token)
            ->getJson('/api/tests/'.$first->id.'/unlock-status')
            ->assertOk()
            ->assertJsonPath('locked', false)
            ->assertJsonPath('code', 'first_in_sequence')
            ->assertJsonPath('completed_attempt', null)
            ->assertJsonPath('required_test', null);

        // The second test requires the first to be completed.
        $this->withToken($token)
            ->getJson('/api/tests/'.$second->id.'/unlock-status')
            ->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('code', 'previous_test_incomplete')
            ->assertJsonPath('required_test.id', $first->id)
            ->assertJsonPath('completed_attempt', null);

        $this->createCompletedAttempt($student, $first);

        $this->withToken($token)
            ->getJson('/api/tests/'.$second->id.'/unlock-status')
            ->assertOk()
            ->assertJsonPath('locked', false)
            ->assertJsonPath('code', 'previous_test_completed')
            ->assertJsonPath('required_test', null);

        // The completed first test reports its own finalized attempt.
        $this->withToken($token)
            ->getJson('/api/tests/'.$first->id.'/unlock-status')
            ->assertOk()
            ->assertJsonPath('locked', false)
            ->assertJsonPath('code', 'completed')
            ->assertJsonPath('completed_attempt.status', 'submitted')
            ->assertJsonPath('completed_attempt.percentage', 80);

        // Draft tests are invisible to students.
        $this->withToken($token)
            ->getJson('/api/tests/'.$draft->id.'/unlock-status')
            ->assertNotFound();
    }

    public function test_draft_tests_are_excluded_from_the_unlock_sequence(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        // A draft with a lower id sits between the two published tests.
        $this->createTest($subject, $author, 'Draft Between', 'draft');
        $second = $this->createTest($subject, $author, 'Published 01');
        $third = $this->createTest($subject, $author, 'Published 02');
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);

        $this->withToken($token)
            ->getJson('/api/tests/'.$third->id.'/unlock-status')
            ->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('required_test.id', $second->id);
    }

    private function createTest(Subject $subject, User $author, string $title, string $status = 'published'): ModelTest
    {
        return ModelTest::query()->create([
            'title' => $title,
            'code' => Str::upper(Str::random(10)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 10,
            'passing_score' => 5,
            'status' => $status,
            'created_by' => $author->id,
        ]);
    }

    private function createCompletedAttempt(User $student, ModelTest $test): ExamAttempt
    {
        return ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(30),
            'expires_at' => now()->addMinutes(30),
            'finished_at' => now()->subMinutes(10),
            'duration_seconds' => 1200,
            'score' => 8,
            'total_marks' => 10,
            'percentage' => 80,
            'correct_count' => 8,
            'incorrect_count' => 2,
            'unanswered_count' => 0,
            'passed' => true,
        ]);
    }

    private function createToken(User $user): string
    {
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Unlock test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }
}
