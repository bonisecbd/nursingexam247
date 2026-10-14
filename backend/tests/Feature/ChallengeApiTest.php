<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChallengeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_routes_require_authentication(): void
    {
        $this->postJson('/api/challenges/1/attempts')->assertUnauthorized();
        $this->getJson('/api/challenges/1/me')->assertUnauthorized();
        $this->postJson('/api/challenges/1/attempts/1/submit')->assertUnauthorized();
        $this->getJson('/api/admin/challenges')->assertUnauthorized();
        $this->postJson('/api/admin/challenges', [])->assertUnauthorized();
        $this->patchJson('/api/admin/challenges/1', [])->assertUnauthorized();

        // Public reads work without a token.
        $this->getJson('/api/challenges')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_challenge_list_and_detail_are_public_and_filtered_by_period(): void
    {
        $daily = $this->createChallenge(['period' => 'daily']);
        $weekly = $this->createChallenge(['period' => 'weekly']);
        $this->createChallenge(['period' => 'daily', 'ends_at' => now()->subHour()]); // ended
        $this->createChallenge(['period' => 'daily', 'is_active' => false]); // disabled
        $this->createChallenge(['period' => 'daily', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]); // upcoming

        // Only currently available challenges are listed.
        $this->getJson('/api/challenges')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/challenges?period=daily')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $daily->id)
            ->assertJsonPath('data.0.period', 'daily');

        $this->getJson('/api/challenges?period=weekly')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $weekly->id);

        $this->getJson('/api/challenges?period=monthly')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
        $this->getJson('/api/challenges?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        // Details stay readable after the window closes, with a safe payload.
        $detail = $this->getJson('/api/challenges/'.$daily->id);
        $detail->assertOk()
            ->assertJsonPath('challenge.id', $daily->id)
            ->assertJsonPath('challenge.status', 'active')
            ->assertJsonPath('challenge.duration_minutes', 10)
            ->assertJsonPath('challenge.attempts_allowed', 1)
            ->assertJsonPath('challenge.reward.points', 20)
            ->assertJsonPath('challenge.reward.wallet_minor', 5000)
            ->assertJsonPath('challenge.reward.currency', 'BDT')
            ->assertJsonPath('challenge.test.id', $daily->test_id)
            ->assertJsonPath('challenge.participant_count', 0)
            ->assertJsonMissingPath('challenge.test.correct_option');
        $this->assertStringStartsWith('daily:', $detail->json('challenge.current_occurrence.key'));

        $ended = Challenge::query()->where('ends_at', '<', now())->firstOrFail();
        $this->getJson('/api/challenges/'.$ended->id)
            ->assertOk()
            ->assertJsonPath('challenge.status', 'ended');

        $this->getJson('/api/challenges/999999')->assertNotFound();
        $this->getJson('/api/challenges/999999/leaderboard')->assertNotFound();
    }

    public function test_admin_can_manage_challenges_and_students_are_denied(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');

        $this->withToken($studentToken)->getJson('/api/admin/challenges')->assertForbidden();
        $this->withToken($studentToken)->postJson('/api/admin/challenges', [])->assertForbidden();
        $this->withToken($studentToken)->patchJson('/api/admin/challenges/1', [])->assertForbidden();

        // Required fields are enforced server-side.
        $this->withToken($adminToken)->postJson('/api/admin/challenges', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'period',
                'test_id',
                'starts_at',
                'ends_at',
                'duration_minutes',
                'attempts_allowed',
            ]);

        $test = $this->createPublishedTest();

        $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'period' => 'monthly',
        ]))->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'ends_at' => now()->subDay(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');

        $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'timezone' => 'Not/AZone',
        ]))->assertUnprocessable()->assertJsonValidationErrors('timezone');

        $draft = ModelTest::query()->create([
            'title' => 'Draft '.Str::uuid(),
            'code' => 'DRAFT'.Str::uuid(),
            'subject_id' => $test->subject_id,
            'duration_minutes' => 10,
            'question_count' => 2,
            'total_marks' => 10,
            'passing_score' => 4,
            'status' => 'draft',
            'created_by' => $test->created_by,
        ]);
        $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'test_id' => $draft->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('test_id');

        $this->assertDatabaseCount('challenges', 0);

        // Valid creation, listing with filters, and update.
        $challengeId = $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'test_id' => $test->id,
            'reward_points' => 25,
            'reward_xp' => 40,
            'reward_wallet_minor' => 7500,
        ]))
            ->assertCreated()
            ->assertJsonPath('challenge.status', 'active')
            ->assertJsonPath('challenge.period', 'daily')
            ->assertJsonPath('challenge.reward.points', 25)
            ->assertJsonPath('challenge.reward.wallet_minor', 7500)
            ->json('challenge.id');

        $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'test_id' => $test->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]))->assertCreated();

        $this->withToken($adminToken)->getJson('/api/admin/challenges')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->withToken($adminToken)->getJson('/api/admin/challenges?status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $challengeId);
        $this->withToken($adminToken)->getJson('/api/admin/challenges?status=upcoming')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->withToken($adminToken)->getJson('/api/admin/challenges?period=weekly')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($adminToken)->patchJson('/api/admin/challenges/'.$challengeId, [
            'title' => 'Renamed challenge',
            'reward_points' => 30,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('challenge.title', 'Renamed challenge')
            ->assertJsonPath('challenge.reward.points', 30)
            ->assertJsonPath('challenge.status', 'disabled');

        $this->withToken($adminToken)->getJson('/api/admin/challenges?status=disabled')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($adminToken)->patchJson('/api/admin/challenges/999999', [
            'title' => 'Nope',
        ])->assertNotFound();
    }

    public function test_challenge_definition_is_frozen_after_students_join(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');
        $test = $this->createPublishedTest();
        $otherTest = $this->createPublishedTest();

        $challengeId = $this->withToken($adminToken)->postJson('/api/admin/challenges', $this->adminPayload([
            'test_id' => $test->id,
        ]))->assertCreated()->json('challenge.id');

        $this->withToken($studentToken)->postJson('/api/challenges/'.$challengeId.'/attempts')
            ->assertCreated();

        // The linked test and the occurrence period cannot change under players.
        $this->withToken($adminToken)->patchJson('/api/admin/challenges/'.$challengeId, [
            'test_id' => $otherTest->id,
        ])->assertConflict();

        $this->withToken($adminToken)->patchJson('/api/admin/challenges/'.$challengeId, [
            'period' => 'weekly',
        ])->assertConflict();

        // Harmless metadata updates still work.
        $this->withToken($adminToken)->patchJson('/api/admin/challenges/'.$challengeId, [
            'description' => 'Updated after join',
        ])->assertOk()->assertJsonPath('challenge.description', 'Updated after join');

        $this->assertSame(1, Challenge::query()->findOrFail($challengeId)->test_id);
    }

    public function test_student_can_start_resume_and_save_answers_on_a_challenge_attempt(): void
    {
        [, $token] = $this->createUserWithToken();
        $challenge = $this->createChallenge();

        $start = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts');
        $start->assertCreated()
            ->assertJsonPath('resumed', false)
            ->assertJsonPath('attempt.status', 'in_progress')
            ->assertJsonPath('attempt.time_remaining_seconds', 600)
            ->assertJsonCount(2, 'attempt.questions')
            ->assertJsonMissingPath('attempt.questions.0.correct_option')
            ->assertJsonMissingPath('attempt.questions.0.explanation');

        $attemptId = $start->json('attempt.id');
        $firstAnswerId = $start->json('attempt.questions.0.id');
        $secondAnswerId = $start->json('attempt.questions.1.id');

        // Answer-save, resume, and navigation come from the Exam module.
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$firstAnswerId}", [
            'selected_option' => 1,
        ])->assertOk()->assertJsonPath('selected_option', 1);

        $this->withToken($token)->getJson("/api/attempts/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('attempt.questions.0.selected_option', 1);

        // A second start resumes instead of opening a second timer.
        $retry = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts');
        $retry->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('attempt.id', $attemptId);

        $me = $this->withToken($token)->getJson('/api/challenges/'.$challenge->id.'/me');
        $me->assertOk()
            ->assertJsonPath('attempts_used', 1)
            ->assertJsonPath('attempts_allowed', 1)
            ->assertJsonPath('occurrence', $start->json('participation.occurrence_key'))
            ->assertJsonPath('live_attempt.id', $attemptId)
            ->assertJsonPath('rank', null)
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.attempt_id', $attemptId)
            ->assertJsonPath('history.0.reward.settled', false);

        $this->assertNotNull($secondAnswerId);
    }

    public function test_challenge_start_enforces_windows_limits_and_attempt_allowance(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();

        $upcoming = $this->createChallenge([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(5),
        ]);
        $ended = $this->createChallenge([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHour(),
        ]);
        $disabled = $this->createChallenge(['is_active' => false]);
        $limited = $this->createChallenge(['attempts_allowed' => 1, 'test_id' => $test->id]);

        $this->withToken($token)->postJson('/api/challenges/'.$upcoming->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
        $this->withToken($token)->postJson('/api/challenges/'.$ended->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
        $this->withToken($token)->postJson('/api/challenges/'.$disabled->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
        $this->withToken($token)->postJson('/api/challenges/999999/attempts')->assertNotFound();

        // The allowed attempt is consumed on start; while it is live, a retry
        // resumes it instead of opening a second timer.
        $attemptId = $this->withToken($token)->postJson('/api/challenges/'.$limited->id.'/attempts')
            ->assertCreated()
            ->json('attempt.id');
        $this->withToken($token)->postJson('/api/challenges/'.$limited->id.'/attempts')
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('attempt.id', $attemptId);

        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('attempt.status', 'submitted');

        // Even after submission the occurrence's attempt budget is exhausted.
        $this->withToken($token)->postJson('/api/challenges/'.$limited->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attempt');
    }

    public function test_premium_challenge_requires_an_active_entitlement(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $challenge = $this->createChallenge(['test_id' => $this->createPublishedTest(['is_premium' => true])->id]);

        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertForbidden();

        $this->grantPremium($user);

        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated();
    }

    public function test_challenge_submission_settles_points_xp_and_wallet_exactly_once(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $challenge = $this->createChallenge([
            'reward_points' => 20,
            'reward_xp' => 30,
            'reward_wallet_minor' => 5000,
        ]);

        $attemptId = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated()
            ->json('attempt.id');
        [$first, $second] = $this->answerIds($attemptId);
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$first}", ['selected_option' => 1])->assertOk();
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$second}", ['selected_option' => 1])->assertOk();

        $submission = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id."/attempts/{$attemptId}/submit");
        $submission->assertOk()
            ->assertJsonPath('attempt.status', 'submitted')
            ->assertJsonPath('result.percentage', '100.00')
            ->assertJsonPath('challenge.id', $challenge->id)
            ->assertJsonPath('challenge.reward.settled', true)
            ->assertJsonPath('challenge.reward.awarded', true)
            ->assertJsonPath('challenge.reward.points', 20)
            ->assertJsonPath('challenge.reward.xp', 30)
            ->assertJsonPath('challenge.reward.wallet_minor', 5000);

        $this->assertDatabaseCount('point_transactions', 2); // test completion + challenge
        $this->assertDatabaseCount('xp_events', 2);
        $this->assertSame(1, $this->challengeLedger('point_transactions')->count());
        $this->assertSame(1, $this->challengeLedger('xp_events')->count());
        $this->assertSame(20, (int) $this->challengeLedger('point_transactions')->value('amount'));
        $this->assertSame(30, (int) $this->challengeLedger('xp_events')->value('amount'));
        $this->assertSame(1, $this->challengeLedger('point_transactions')->where('reward_type', 'like', 'challenge_daily_%')->count());

        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'challenge')->count());
        $this->assertSame(5000, (int) DB::table('wallet_transactions')->where('source_type', 'challenge')->value('amount_minor'));
        $this->withToken($token)->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.balance_minor', 5000);

        // Retrying the submission returns the stored outcome without a second credit.
        $retry = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id."/attempts/{$attemptId}/submit");
        $retry->assertOk()
            ->assertJsonPath('attempt.status', 'submitted')
            ->assertJsonPath('attempt.finished_at', $submission->json('attempt.finished_at'))
            ->assertJsonPath('challenge.reward.awarded', true)
            ->assertJsonPath('challenge.reward.points', 20)
            ->assertJsonPath('challenge.reward.wallet_minor', 5000);

        $this->assertSame(1, $this->challengeLedger('point_transactions')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'challenge')->count());
        $this->withToken($token)->getJson('/api/wallet')
            ->assertJsonPath('wallet.balance_minor', 5000);
    }

    public function test_reward_settles_when_the_attempt_is_submitted_through_the_exam_endpoint(): void
    {
        [, $token] = $this->createUserWithToken();
        $challenge = $this->createChallenge([
            'reward_points' => 15,
            'reward_xp' => 25,
            'reward_wallet_minor' => 2500,
        ]);

        $attemptId = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated()
            ->json('attempt.id');
        [$first, $second] = $this->answerIds($attemptId);
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$first}", ['selected_option' => 1])->assertOk();
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$second}", ['selected_option' => 1])->assertOk();

        // The plain Exam module submit finalizes the attempt but does not
        // settle the challenge yet: settlement is lazy and idempotent.
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('attempt.status', 'submitted')
            ->assertJsonMissingPath('challenge');

        $this->assertNull(DB::table('challenge_participations')->value('settled_at'));
        $this->assertSame(0, $this->challengeLedger('point_transactions')->count());

        // Reading the standings sweeps settlement before ranking.
        $this->getJson('/api/challenges/'.$challenge->id.'/leaderboard')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.score', 100);

        $this->assertNotNull(DB::table('challenge_participations')->value('settled_at'));
        $this->assertSame(1, $this->challengeLedger('point_transactions')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'challenge')->count());

        $me = $this->withToken($token)->getJson('/api/challenges/'.$challenge->id.'/me');
        $me->assertOk()
            ->assertJsonPath('rank', 1)
            ->assertJsonPath('score', 100)
            ->assertJsonPath('history.0.reward.settled', true)
            ->assertJsonPath('history.0.reward.awarded', true)
            ->assertJsonPath('history.0.reward.points', 15);

        // Re-submitting through either path never duplicates the reward.
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")->assertOk();
        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id."/attempts/{$attemptId}/submit")->assertOk();
        $this->assertSame(1, $this->challengeLedger('point_transactions')->count());
        $this->assertSame(1, $this->challengeLedger('xp_events')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'challenge')->count());
        $this->withToken($token)->getJson('/api/wallet')
            ->assertJsonPath('wallet.balance_minor', 2500);
    }

    public function test_only_the_first_completed_attempt_of_an_occurrence_earns_the_reward(): void
    {
        [, $token] = $this->createUserWithToken();
        $challenge = $this->createChallenge([
            'attempts_allowed' => 2,
            'reward_points' => 20,
            'reward_xp' => 30,
            'reward_wallet_minor' => 5000,
        ]);

        $firstAttempt = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated()
            ->json('attempt.id');
        [$q1, $q2] = $this->answerIds($firstAttempt);
        $this->withToken($token)->putJson("/api/attempts/{$firstAttempt}/answers/{$q1}", ['selected_option' => 1])->assertOk();
        $this->withToken($token)->putJson("/api/attempts/{$firstAttempt}/answers/{$q2}", ['selected_option' => 1])->assertOk();
        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id."/attempts/{$firstAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('challenge.reward.awarded', true);

        $secondAttempt = $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated()
            ->assertJsonPath('resumed', false)
            ->json('attempt.id');
        $this->assertNotSame($firstAttempt, $secondAttempt);
        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id."/attempts/{$secondAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('challenge.reward.awarded', false)
            ->assertJsonPath('challenge.reward.points', 0)
            ->assertJsonPath('challenge.reward.wallet_minor', 0);

        // Exactly one ledger entry and one wallet credit per occurrence.
        $this->assertSame(1, $this->challengeLedger('point_transactions')->count());
        $this->assertSame(1, $this->challengeLedger('xp_events')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'challenge')->count());

        $rewarded = DB::table('challenge_participations')->orderBy('id')->pluck('rewarded')->map(fn ($value) => (bool) $value);
        $this->assertSame([true, false], $rewarded->all());

        // Both attempts count toward this student's standings.
        $this->getJson('/api/challenges/'.$challenge->id.'/leaderboard')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.attempt_count', 2)
            ->assertJsonPath('data.0.score', 100);
    }

    public function test_challenge_leaderboard_ranks_finalized_attempts_and_hides_unfinished_or_inactive_students(): void
    {
        $challenge = $this->createChallenge([
            'reward_points' => 0,
            'reward_xp' => 0,
            'reward_wallet_minor' => 0,
        ]);
        [$alice, $aliceToken] = $this->createUserWithToken();
        [$cara, $caraToken] = $this->createUserWithToken();
        [$bob, $bobToken] = $this->createUserWithToken();
        [$dana, $danaToken] = $this->createUserWithToken();
        [$eve, $eveToken] = $this->createUserWithToken();
        User::query()->whereKey($alice->id)->update(['name' => 'Alice']);
        User::query()->whereKey($cara->id)->update(['name' => 'Cara']);
        User::query()->whereKey($bob->id)->update(['name' => 'Bob']);
        User::query()->whereKey($dana->id)->update(['name' => 'Dana']);
        User::query()->whereKey($eve->id)->update(['name' => 'Eve']);

        // Alice: perfect submission (100%).
        $aliceAttempt = $this->withToken($aliceToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')->json('attempt.id');
        [$a1, $a2] = $this->answerIds($aliceAttempt);
        $this->withToken($aliceToken)->putJson("/api/attempts/{$aliceAttempt}/answers/{$a1}", ['selected_option' => 1])->assertOk();
        $this->withToken($aliceToken)->putJson("/api/attempts/{$aliceAttempt}/answers/{$a2}", ['selected_option' => 1])->assertOk();
        $this->withToken($aliceToken)->postJson('/api/attempts/'.$aliceAttempt.'/submit')->assertOk();

        // Cara: started but never submitted (must stay hidden).
        $caraAttempt = $this->withToken($caraToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')->assertCreated()->json('attempt.id');

        // Bob: one right, one wrong (47.50%).
        $bobAttempt = $this->withToken($bobToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')->json('attempt.id');
        [$b1, $b2] = $this->answerIds($bobAttempt);
        $this->withToken($bobToken)->putJson("/api/attempts/{$bobAttempt}/answers/{$b1}", ['selected_option' => 1])->assertOk();
        $this->withToken($bobToken)->putJson("/api/attempts/{$bobAttempt}/answers/{$b2}", ['selected_option' => 2])->assertOk();
        $this->withToken($bobToken)->postJson('/api/attempts/'.$bobAttempt.'/submit')->assertOk();

        // Eve: submitted, then deactivated (must disappear from standings).
        $eveAttempt = $this->withToken($eveToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')->json('attempt.id');
        $this->withToken($eveToken)->postJson('/api/attempts/'.$eveAttempt.'/submit')->assertOk();
        User::query()->whereKey($eve->id)->update(['is_active' => false]);

        // Dana starts, then her timer runs out: expired but ranked (0%).
        $danaAttempt = $this->withToken($danaToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')->json('attempt.id');
        DB::table('attempts')->where('id', $danaAttempt)->update(['expires_at' => now()->subMinute()]);
        $this->withToken($danaToken)->postJson('/api/attempts/'.$danaAttempt.'/submit')
            ->assertOk()
            ->assertJsonPath('attempt.status', 'expired');

        $board = $this->getJson('/api/challenges/'.$challenge->id.'/leaderboard?per_page=2');
        $board->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.display_name', 'Alice')
            ->assertJsonPath('data.0.score', 100)
            ->assertJsonPath('data.0.avatar_url', null)
            ->assertJsonPath('data.1.rank', 2)
            ->assertJsonPath('data.1.display_name', 'Bob')
            ->assertJsonPath('data.1.score', 47.5);

        $full = $this->getJson('/api/challenges/'.$challenge->id.'/leaderboard');
        $full->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.display_name', 'Alice')
            ->assertJsonPath('data.1.display_name', 'Bob')
            ->assertJsonPath('data.2.display_name', 'Dana')
            ->assertJsonPath('data.2.score', 0);

        $names = collect($full->json('data'))->pluck('display_name')->all();
        $this->assertNotContains('Cara', $names);
        $this->assertNotContains('Eve', $names);
        $this->assertNotNull($caraAttempt);
    }

    public function test_challenge_attempt_and_submission_are_owner_scoped(): void
    {
        [, $ownerToken] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $challenge = $this->createChallenge();

        $attemptId = $this->withToken($ownerToken)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertCreated()
            ->json('attempt.id');

        // Another student cannot submit (or even confirm) someone else's entry.
        $this->withToken($otherToken)->postJson('/api/challenges/'.$challenge->id."/attempts/{$attemptId}/submit")
            ->assertNotFound();

        // A normal exam attempt with no participation cannot be submitted here.
        $test = $this->createPublishedTest();
        $plainAttempt = $this->withToken($ownerToken)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $this->withToken($ownerToken)->postJson('/api/challenges/'.$challenge->id."/attempts/{$plainAttempt}/submit")
            ->assertNotFound();

        // The other student's personal state is their own and still empty.
        $this->withToken($otherToken)->getJson('/api/challenges/'.$challenge->id.'/me')
            ->assertOk()
            ->assertJsonPath('attempts_used', 0)
            ->assertJsonPath('rank', null)
            ->assertJsonCount(0, 'history');

        // The owner's attempt was untouched by the failed cross-user submit.
        $this->withToken($ownerToken)->getJson('/api/attempts/'.$attemptId)
            ->assertOk()
            ->assertJsonPath('attempt.status', 'in_progress');
    }

    public function test_challenge_start_rejects_unpublished_or_broken_question_sets(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $challenge = $this->createChallenge(['test_id' => $test->id]);

        $test->update(['status' => 'draft']);
        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');

        $test->update(['status' => 'published']);
        Question::query()->where('subject_id', $test->subject_id)->where('is_active', true)->limit(1)->update(['is_active' => false]);
        $this->withToken($token)->postJson('/api/challenges/'.$challenge->id.'/attempts')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');

        $this->assertDatabaseCount('challenge_participations', 0);
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'challenge-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Challenge test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function createPublishedTest(array $overrides = []): ModelTest
    {
        $subject = Subject::query()->create([
            'name' => 'Nursing '.Str::uuid(),
            'code' => 'NUR'.Str::uuid(),
        ]);
        $creator = User::factory()->create(['role' => 'admin']);
        $questions = collect(range(1, 2))->map(fn (int $number): Question => Question::query()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Challenge question '.$number,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1,
            'explanation' => 'Option A is correct.',
            'difficulty' => 'medium',
            'is_active' => true,
        ]));

        $test = ModelTest::query()->create(array_merge([
            'title' => 'Challenge Test '.Str::uuid(),
            'code' => 'CHT'.Str::uuid(),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 2,
            'total_marks' => 10,
            'passing_score' => 4,
            'negative_marking' => 0.25,
            'is_negative_marking_enabled' => true,
            'is_premium' => false,
            'status' => 'published',
            'created_by' => $creator->id,
        ], $overrides));

        $test->questions()->sync([
            $questions[0]->id => ['sequence' => 1, 'points' => 5],
            $questions[1]->id => ['sequence' => 2, 'points' => 5],
        ]);

        return $test;
    }

    private function createChallenge(array $overrides = []): Challenge
    {
        if (! array_key_exists('test_id', $overrides)) {
            $overrides['test_id'] = $this->createPublishedTest()->id;
        }

        return Challenge::query()->create(array_merge([
            'title' => 'Daily Challenge '.Str::uuid(),
            'description' => 'Beat the clock.',
            'period' => 'daily',
            'timezone' => 'Asia/Dhaka',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(3),
            'duration_minutes' => 10,
            'attempts_allowed' => 1,
            'reward_points' => 20,
            'reward_xp' => 30,
            'reward_wallet_minor' => 5000,
            'is_active' => true,
        ], $overrides));
    }

    private function adminPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Weekly Sprint',
            'period' => 'daily',
            'test_id' => $this->createPublishedTest()->id,
            'timezone' => 'Asia/Dhaka',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(3),
            'duration_minutes' => 15,
            'attempts_allowed' => 2,
            'reward_points' => 10,
            'reward_xp' => 10,
            'reward_wallet_minor' => 1000,
        ], $overrides);
    }

    private function answerIds(int $attemptId): array
    {
        return DB::table('attempt_answers')
            ->where('attempt_id', $attemptId)
            ->orderBy('sequence')
            ->pluck('id')
            ->all();
    }

    private function challengeLedger(string $table): Builder
    {
        return DB::table($table)->where('source_type', 'challenge');
    }

    private function grantPremium(User $user): void
    {
        $packageId = (int) DB::table('subscription_packages')
            ->where('code', 'premium-monthly')
            ->value('id');

        DB::table('subscriptions')->insert([
            'user_id' => $user->id,
            'package_id' => $packageId,
            'order_id' => null,
            'status' => 'active',
            'source' => 'payment',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
