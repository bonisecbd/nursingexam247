<?php

namespace Tests\Feature;

use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminQuestionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_filter_question_bank_and_see_linked_test_counts(): void
    {
        $editorToken = $this->createUserWithToken('editor');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $question = $this->createQuestion($subject, [
            'question_text' => 'How does a nurse assess airway?',
            'difficulty' => 'easy',
        ]);
        $this->createQuestion($subject, [
            'question_text' => 'Explain medication safety.',
            'difficulty' => 'hard',
            'is_active' => false,
        ]);
        $test = $this->createTest($subject, 'draft');
        $test->questions()->attach($question->id, ['sequence' => 1, 'points' => 1]);

        $this->withToken($editorToken)->getJson('/api/admin/questions?search=airway&difficulty=easy&status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.active', 1)
            ->assertJsonPath('meta.inactive', 1)
            ->assertJsonPath('data.0.question_text', 'How does a nurse assess airway?')
            ->assertJsonPath('data.0.subject.code', 'NUR')
            ->assertJsonPath('data.0.tests_count', 1)
            ->assertJsonPath('data.0.published_tests_count', 0)
            ->assertJsonPath('data.0.correct_option', 2)
            ->assertJsonCount(4, 'data.0.options');

        $this->withToken($editorToken)->getJson('/api/admin/questions?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.0.difficulty', 'hard')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_staff_can_create_and_update_questions_with_valid_answers(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);

        $created = $this->withToken($adminToken)->postJson('/api/admin/questions', [
            'subject_id' => $subject->id,
            'question_text' => 'What is the first nursing action?',
            'options' => ['Assess the patient', 'Call the provider'],
            'correct_option' => 1,
            'explanation' => 'Assess before intervening.',
            'difficulty' => 'medium',
        ])->assertCreated()
            ->assertJsonPath('message', 'Question created successfully.')
            ->assertJsonPath('question.is_active', true)
            ->assertJsonMissingPath('question.created_by');

        $questionId = $created->json('question.id');
        $this->withToken($adminToken)->patchJson("/api/admin/questions/{$questionId}", [
            'subject_id' => $subject->id,
            'question_text' => 'What should the nurse assess first?',
            'options' => ['Airway', 'Nutrition'],
            'correct_option' => 1,
            'explanation' => null,
            'difficulty' => 'easy',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('message', 'Question updated successfully.')
            ->assertJsonPath('question.question_text', 'What should the nurse assess first?')
            ->assertJsonPath('question.is_active', false);

        $this->assertDatabaseHas('questions', [
            'id' => $questionId,
            'correct_option' => 1,
            'difficulty' => 'easy',
            'is_active' => false,
        ]);
    }

    public function test_question_validation_rejects_invalid_options_answers_subjects_and_difficulty(): void
    {
        $editorToken = $this->createUserWithToken('editor');
        $inactiveSubject = Subject::query()->create([
            'name' => 'Inactive subject',
            'code' => 'INA',
            'is_active' => false,
        ]);

        $this->withToken($editorToken)->postJson('/api/admin/questions', [
            'subject_id' => $inactiveSubject->id,
            'question_text' => 'A question',
            'options' => ['One', 'Two'],
            'correct_option' => 3,
            'difficulty' => 'expert',
        ])->assertUnprocessable()->assertJsonValidationErrors('difficulty');

        $this->withToken($editorToken)->postJson('/api/admin/questions', [
            'subject_id' => $inactiveSubject->id,
            'question_text' => 'A question',
            'options' => ['One', 'Two'],
            'correct_option' => 3,
            'difficulty' => 'easy',
        ])->assertUnprocessable()->assertJsonValidationErrors('correct_option');

        $this->withToken($editorToken)->postJson('/api/admin/questions', [
            'subject_id' => $inactiveSubject->id,
            'question_text' => 'A question',
            'options' => ['One', 'Two'],
            'correct_option' => 1,
            'difficulty' => 'easy',
        ])->assertUnprocessable()->assertJsonValidationErrors('subject_id');

        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $this->withToken($editorToken)->postJson('/api/admin/questions', [
            'subject_id' => $subject->id,
            'question_text' => 'A question',
            'options' => ['Only one'],
            'correct_option' => 1,
            'difficulty' => 'easy',
        ])->assertUnprocessable()->assertJsonValidationErrors('options');
    }

    public function test_questions_used_by_published_tests_cannot_be_edited_or_deactivated(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $question = $this->createQuestion($subject);
        $test = $this->createTest($subject, 'published');
        $test->questions()->attach($question->id, ['sequence' => 1, 'points' => 1]);

        $this->withToken($adminToken)->patchJson("/api/admin/questions/{$question->id}", [
            'question_text' => 'Changed content',
        ])->assertUnprocessable()->assertJsonValidationErrors('question');

        $this->withToken($adminToken)->patchJson("/api/admin/questions/{$question->id}", [
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('question');

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'question_text' => 'What is the best response?',
            'is_active' => true,
        ]);
    }

    public function test_question_management_is_restricted_to_admins_and_editors(): void
    {
        $studentToken = $this->createUserWithToken('student');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $question = $this->createQuestion($subject);

        $this->getJson('/api/admin/questions')->assertUnauthorized();
        $this->withToken($studentToken)->getJson('/api/admin/questions')->assertForbidden();
        $this->withToken($studentToken)->postJson('/api/admin/questions', [
            'subject_id' => $subject->id,
            'question_text' => 'Not allowed',
            'options' => ['One', 'Two'],
            'correct_option' => 1,
            'difficulty' => 'easy',
        ])->assertForbidden();
        $this->withToken($studentToken)->patchJson("/api/admin/questions/{$question->id}", [
            'is_active' => false,
        ])->assertForbidden();
    }

    private function createQuestion(Subject $subject, array $overrides = []): Question
    {
        return Question::query()->create(array_merge([
            'subject_id' => $subject->id,
            'question_text' => 'What is the best response?',
            'options' => ['Listen', 'Assess', 'Document', 'Refer'],
            'correct_option' => 2,
            'explanation' => 'Assess the patient first.',
            'difficulty' => 'medium',
            'is_active' => true,
        ], $overrides));
    }

    private function createTest(Subject $subject, string $status): ModelTest
    {
        return ModelTest::query()->create([
            'title' => 'Nursing test',
            'code' => Str::upper(Str::random(12)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => $status,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
    }

    private function createUserWithToken(string $role): string
    {
        $user = User::factory()->create(['role' => $role]);
        $plainTextToken = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Question management test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainTextToken;
    }
}
