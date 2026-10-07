<?php

namespace Tests\Feature;

use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_assign_and_publish_a_model_test_without_leaking_answers(): void
    {
        [$admin, $token] = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $questions = $this->createQuestions($subject, 2);

        $create = $this->withToken($token)->postJson('/api/admin/tests', [
            'title' => 'Nursing Model Test 01',
            'subject_id' => $subject->id,
            'duration_minutes' => 60,
            'question_count' => 2,
            'total_marks' => 10,
            'passing_score' => 4,
            'negative_marking' => 0.25,
            'question_ids' => $questions->pluck('id')->all(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('test.status', 'draft')
            ->assertJsonPath('test.is_premium', false);

        $testId = $create->json('test.id');

        $this->getJson('/api/tests/'.$testId)->assertNotFound();
        $this->withToken($token)->postJson('/api/admin/tests/'.$testId.'/publish')
            ->assertOk()
            ->assertJsonPath('test.status', 'published');

        $this->getJson('/api/tests/'.$testId)
            ->assertOk()
            ->assertJsonPath('test.questions.0.points', 5)
            ->assertJsonMissingPath('test.questions.0.correct_option')
            ->assertJsonMissingPath('test.questions.0.explanation');

        $this->getJson('/api/tests?subject_id='.$subject->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $testId);
    }

    public function test_only_admins_and_editors_can_manage_model_tests(): void
    {
        [, $token] = $this->createUserWithToken('student');

        $this->withToken($token)->postJson('/api/admin/tests', [
            'title' => 'Forbidden Test',
            'subject_id' => 1,
            'duration_minutes' => 60,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 0,
        ])->assertForbidden();
    }

    public function test_publishing_requires_the_exact_number_of_active_questions_from_the_selected_subject(): void
    {
        [, $adminToken] = $this->createUserWithToken('editor');
        $nursing = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
        $question = $this->createQuestions($nursing, 1)->first();
        $test = $this->createDraftTest($adminToken, $nursing, [$question->id], [
            'question_count' => 2,
        ]);

        $this->withToken($adminToken)->postJson('/api/admin/tests/'.$test->id.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question_ids');

        $otherSubjectQuestion = $this->createQuestions($english, 1)->first();
        $this->withToken($adminToken)->putJson('/api/admin/tests/'.$test->id.'/questions', [
            'question_ids' => [$question->id, $otherSubjectQuestion->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('question_ids');
    }

    public function test_published_tests_cannot_be_edited(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $question = $this->createQuestions($subject, 1)->first();
        $test = $this->createDraftTest($adminToken, $subject, [$question->id]);

        $this->withToken($adminToken)->postJson('/api/admin/tests/'.$test->id.'/publish')->assertOk();
        $this->withToken($adminToken)->patchJson('/api/admin/tests/'.$test->id, [
            'title' => 'Changed title',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('tests', [
            'id' => $test->id,
            'title' => $test->title,
            'status' => 'published',
        ]);
    }

    public function test_premium_test_questions_are_not_available_without_subscription_access(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $question = $this->createQuestions($subject, 1)->first();
        $test = $this->createDraftTest($adminToken, $subject, [$question->id], [
            'is_premium' => true,
        ]);

        $this->withToken($adminToken)->postJson('/api/admin/tests/'.$test->id.'/publish')->assertOk();
        $this->getJson('/api/tests/'.$test->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'This premium test requires an active premium subscription.');
    }

    private function createUserWithToken(string $role): array
    {
        $user = User::factory()->create(['role' => $role]);
        $plainTextToken = $role.'-token';
        $user->apiTokens()->create([
            'name' => 'Test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $plainTextToken];
    }

    private function createQuestions(Subject $subject, int $count)
    {
        return collect(range(1, $count))->map(fn (int $number): Question => Question::query()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Question '.$number,
            'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_option' => 1,
            'explanation' => 'Because option A is correct.',
            'difficulty' => 'medium',
            'is_active' => true,
        ]));
    }

    private function createDraftTest(string $token, Subject $subject, array $questionIds, array $overrides = []): ModelTest
    {
        $payload = array_merge([
            'title' => 'Nursing Model Test',
            'subject_id' => $subject->id,
            'duration_minutes' => 60,
            'question_count' => count($questionIds),
            'total_marks' => count($questionIds),
            'passing_score' => 1,
            'question_ids' => $questionIds,
        ], $overrides);

        $response = $this->withToken($token)->postJson('/api/admin/tests', $payload);
        $response->assertCreated();

        return ModelTest::query()->findOrFail($response->json('test.id'));
    }
}
