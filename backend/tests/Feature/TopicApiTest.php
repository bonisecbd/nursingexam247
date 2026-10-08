<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TopicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_topics_are_publicly_listed_and_filterable_by_subject(): void
    {
        $nursing = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
        Topic::query()->create(['subject_id' => $nursing->id, 'name' => 'Airway']);
        Topic::query()->create(['subject_id' => $nursing->id, 'name' => 'Injection']);
        Topic::query()->create(['subject_id' => $english->id, 'name' => 'Grammar']);

        $this->getJson('/api/topics')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/topics?subject_id='.$english->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grammar')
            ->assertJsonPath('data.0.subject.name', 'English');

        $this->getJson('/api/topics?subject_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject_id');
    }

    public function test_only_staff_can_create_and_update_topics(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $inactiveSubject = Subject::query()->create([
            'name' => 'Retired',
            'code' => 'RET',
            'is_active' => false,
        ]);
        $studentToken = $this->createToken(User::factory()->create(['role' => 'student']), 'Student');
        $adminToken = $this->createToken(User::factory()->create(['role' => 'admin']), 'Admin');

        $payload = ['subject_id' => $subject->id, 'name' => 'Airway Management'];

        $this->postJson('/api/topics', $payload)->assertUnauthorized();

        $this->withToken($studentToken)
            ->postJson('/api/topics', $payload)
            ->assertForbidden();

        $this->withToken($adminToken)
            ->postJson('/api/topics', ['subject_id' => $inactiveSubject->id, 'name' => 'Legacy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject_id');

        $topicId = $this->withToken($adminToken)
            ->postJson('/api/topics', $payload)
            ->assertCreated()
            ->assertJsonPath('topic.name', 'Airway Management')
            ->assertJsonPath('topic.is_active', true)
            ->json('topic.id');

        $this->withToken($adminToken)
            ->postJson('/api/topics', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->withToken($studentToken)
            ->patchJson('/api/topics/'.$topicId, ['name' => 'Renamed'])
            ->assertForbidden();

        $this->withToken($adminToken)
            ->patchJson('/api/topics/'.$topicId, ['name' => 'Ventilation', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('topic.name', 'Ventilation')
            ->assertJsonPath('topic.is_active', false);

        // Deactivated topics disappear from the student-facing list.
        $this->getJson('/api/topics')->assertOk()->assertJsonCount(0, 'data');
    }

    private function createToken(User $user, string $label): string
    {
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => $label.' topic test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }
}
