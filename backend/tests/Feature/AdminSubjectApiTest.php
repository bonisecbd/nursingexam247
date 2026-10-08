<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSubjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_all_subjects_with_question_and_test_counts(): void
    {
        $editorToken = $this->createUserWithToken('editor');
        $active = Subject::query()->create([
            'name' => 'Nursing',
            'code' => 'NUR',
        ]);
        Subject::query()->create([
            'name' => 'Archived Subject',
            'code' => 'ARC',
            'is_active' => false,
        ]);

        $this->withToken($editorToken)->getJson('/api/admin/subjects?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.active', 1)
            ->assertJsonPath('meta.inactive', 1)
            ->assertJsonPath('data.0.code', 'ARC');

        $this->withToken($editorToken)->getJson('/api/admin/subjects?search=nur')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.questions_count', 0)
            ->assertJsonPath('data.0.tests_count', 0);
    }

    public function test_staff_can_create_and_update_a_subject(): void
    {
        $adminToken = $this->createUserWithToken('admin');

        $created = $this->withToken($adminToken)->postJson('/api/admin/subjects', [
            'name' => 'Nursing',
            'code' => 'nur',
            'description' => 'Nursing preparation',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('message', 'Subject created successfully.')
            ->assertJsonPath('subject.name', 'Nursing')
            ->assertJsonPath('subject.code', 'NUR')
            ->assertJsonPath('subject.questions_count', 0);

        $subjectId = $created->json('subject.id');
        $this->withToken($adminToken)->patchJson("/api/admin/subjects/{$subjectId}", [
            'name' => 'Nursing Science',
            'code' => 'NUR',
            'description' => 'Updated description',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('subject.name', 'Nursing Science')
            ->assertJsonPath('subject.is_active', false);

        $this->getJson('/api/subjects')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/subjects/{$subjectId}")->assertNotFound();
    }

    public function test_subject_validation_rejects_duplicate_codes_and_invalid_fields(): void
    {
        $adminToken = $this->createUserWithToken('admin');
        Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);

        $this->withToken($adminToken)->postJson('/api/admin/subjects', [
            'name' => 'Nursing Basics',
            'code' => 'NUR',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->withToken($adminToken)->postJson('/api/admin/subjects', [
            'name' => '',
            'code' => 'bad code',
            'description' => str_repeat('a', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'description']);
    }

    public function test_subject_management_requires_admin_or_editor_role(): void
    {
        $studentToken = $this->createUserWithToken('student');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);

        $this->getJson('/api/admin/subjects')->assertUnauthorized();
        $this->withToken($studentToken)->getJson('/api/admin/subjects')->assertForbidden();
        $this->withToken($studentToken)->postJson('/api/admin/subjects', [
            'name' => 'English',
            'code' => 'ENG',
        ])->assertForbidden();
        $this->withToken($studentToken)->patchJson("/api/admin/subjects/{$subject->id}", [
            'name' => 'Changed',
            'code' => 'CHG',
        ])->assertForbidden();
    }

    private function createUserWithToken(string $role): string
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
        $plainTextToken = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Subject management test token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainTextToken;
    }
}
