<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAttemptController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_id' => ['required', 'integer', 'exists:tests,id'],
        ]);

        $attempt = DB::transaction(function () use ($request, $validated): ExamAttempt {
            $test = ModelTest::query()->lockForUpdate()->findOrFail($validated['test_id']);

            if ($test->status !== 'published') {
                throw ValidationException::withMessages([
                    'test_id' => ['Only published tests can be started.'],
                ]);
            }

            if ($test->is_premium) {
                abort(403, 'This premium test requires an active premium subscription.');
            }

            $questions = $test->questions()
                ->where('questions.is_active', true)
                ->get();

            if ($questions->count() !== $test->question_count) {
                throw ValidationException::withMessages([
                    'test_id' => ['This test is unavailable because its published question set is incomplete.'],
                ]);
            }

            foreach ($questions as $question) {
                if (
                    ! is_array($question->options)
                    || count($question->options) < 2
                    || $question->correct_option < 1
                    || $question->correct_option > count($question->options)
                ) {
                    throw ValidationException::withMessages([
                        'test_id' => ['This test contains a question with invalid answer options.'],
                    ]);
                }
            }

            $startedAt = now();
            $attempt = ExamAttempt::query()->create([
                'user_id' => $request->user()->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addMinutes($test->duration_minutes),
                'total_marks' => $test->total_marks,
            ]);

            foreach ($questions as $question) {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'sequence' => $question->pivot->sequence,
                    'question_text' => $question->question_text,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'explanation' => $question->explanation,
                    'points' => $question->pivot->points,
                ]);
            }

            return $attempt;
        });

        return response()->json([
            'attempt' => $this->attemptData($attempt->load(['test:id,title,duration_minutes', 'answers'])),
        ], 201);
    }

    public function resume(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attempt);

        if ($attempt->status === 'in_progress' && now()->greaterThanOrEqualTo($attempt->expires_at)) {
            $attempt = $this->finish($attempt, 'expired');
        }

        return response()->json([
            'attempt' => $this->attemptData($attempt->load(['test:id,title,duration_minutes', 'answers'])),
        ]);
    }

    public function question(Request $request, int $attempt, int $question): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attempt);
        $answer = $attempt->answers()->whereKey($question)->firstOrFail();

        if ($attempt->status === 'in_progress' && now()->greaterThanOrEqualTo($attempt->expires_at)) {
            $attempt = $this->finish($attempt, 'expired');

            return response()->json([
                'message' => 'The exam time has expired.',
                'attempt' => $this->attemptData($attempt->load('test:id,title,duration_minutes')),
            ], 410);
        }

        abort_unless($attempt->status === 'in_progress', 409, 'This attempt is already finished.');

        return response()->json([
            'question' => $this->questionData($answer, includeNavigation: true),
            'time_remaining_seconds' => $this->secondsRemaining($attempt),
        ]);
    }

    public function saveAnswer(Request $request, int $attempt, int $question): JsonResponse
    {
        $validated = $request->validate([
            'selected_option' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $attempt = $this->ownedAttempt($request, $attempt);

        return DB::transaction(function () use ($attempt, $question, $validated): JsonResponse {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $answer = $lockedAttempt->answers()->whereKey($question)->firstOrFail();

            if ($lockedAttempt->status !== 'in_progress') {
                abort(409, 'This attempt is already finished.');
            }

            if (now()->greaterThanOrEqualTo($lockedAttempt->expires_at)) {
                $finished = $this->finish($lockedAttempt, 'expired');

                return response()->json([
                    'message' => 'The exam time has expired.',
                    'attempt' => $this->attemptData($finished),
                ], 410);
            }

            $selectedOption = $validated['selected_option'];

            if ($selectedOption !== null && $selectedOption > count($answer->options)) {
                throw ValidationException::withMessages([
                    'selected_option' => ['The selected option does not exist for this question.'],
                ]);
            }

            $answer->update([
                'selected_option' => $selectedOption,
                'answered_at' => $selectedOption === null ? null : now(),
            ]);

            return response()->json([
                'message' => 'Answer saved.',
                'question_id' => $answer->id,
                'selected_option' => $answer->selected_option,
                'saved_at' => $answer->updated_at->toISOString(),
                'time_remaining_seconds' => $this->secondsRemaining($lockedAttempt),
            ]);
        });
    }

    public function submit(Request $request, int $attempt, GamificationService $gamification): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attempt);
        $submission = DB::transaction(function () use ($attempt, $gamification): array {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($lockedAttempt->status === 'in_progress') {
                $status = now()->greaterThanOrEqualTo($lockedAttempt->expires_at) ? 'expired' : 'submitted';
                $finishedAttempt = $this->finish($lockedAttempt, $status);

                return [
                    'attempt' => $finishedAttempt,
                    'reward' => $gamification->awardTestCompletion($finishedAttempt),
                ];
            }

            return ['attempt' => $lockedAttempt, 'reward' => null];
        });
        $attempt = $submission['attempt'];

        return response()->json([
            'message' => $attempt->status === 'expired' ? 'The exam time expired.' : 'Exam submitted successfully.',
            'attempt' => $this->attemptData($attempt->load('test:id,title,duration_minutes')),
            'result' => $this->resultData($attempt),
            'reward' => $submission['reward'],
        ]);
    }

    private function ownedAttempt(Request $request, int $attemptId): ExamAttempt
    {
        return ExamAttempt::query()
            ->where('user_id', $request->user()->id)
            ->with('test:id,title,duration_minutes')
            ->findOrFail($attemptId);
    }

    private function finish(ExamAttempt $attempt, string $status): ExamAttempt
    {
        $attempt->loadMissing('answers');
        $correctCount = 0;
        $incorrectCount = 0;
        $unansweredCount = 0;
        $scoreCents = 0;
        $test = $attempt->test()->firstOrFail();
        $negativePenaltyCents = $test->is_negative_marking_enabled
            ? (int) round((float) $test->negative_marking * 100)
            : 0;

        foreach ($attempt->answers as $answer) {
            if ($answer->selected_option === null) {
                $unansweredCount++;
            } elseif ($answer->selected_option === $answer->correct_option) {
                $correctCount++;
                $scoreCents += (int) round((float) $answer->points * 100);
            } else {
                $incorrectCount++;
                $scoreCents -= $negativePenaltyCents;
            }
        }

        $scoreCents = max(0, $scoreCents);
        $totalMarksCents = (int) round((float) $attempt->total_marks * 100);
        $score = $scoreCents / 100;
        $totalMarks = $totalMarksCents / 100;
        $percentage = $totalMarksCents > 0 ? round($scoreCents / $totalMarksCents * 100, 2) : 0;

        $attempt->update([
            'status' => $status,
            'finished_at' => now(),
            'duration_seconds' => min(
                (int) floor($attempt->started_at->diffInSeconds(now())),
                (int) floor($attempt->started_at->diffInSeconds($attempt->expires_at)),
            ),
            'score' => number_format($score, 2, '.', ''),
            'percentage' => number_format($percentage, 2, '.', ''),
            'correct_count' => $correctCount,
            'incorrect_count' => $incorrectCount,
            'unanswered_count' => $unansweredCount,
            'passed' => $score >= (float) $test->passing_score,
        ]);

        return $attempt->fresh(['test:id,title,duration_minutes']);
    }

    private function attemptData(ExamAttempt $attempt): array
    {
        $data = [
            'id' => $attempt->id,
            'test' => $attempt->test ? [
                'id' => $attempt->test->id,
                'title' => $attempt->test->title,
                'duration_minutes' => $attempt->test->duration_minutes,
            ] : null,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at->toISOString(),
            'expires_at' => $attempt->expires_at->toISOString(),
            'finished_at' => $attempt->finished_at?->toISOString(),
            'time_remaining_seconds' => $attempt->status === 'in_progress'
                ? $this->secondsRemaining($attempt)
                : 0,
        ];

        if ($attempt->relationLoaded('answers')) {
            $data['questions'] = $attempt->answers
                ->map(fn (AttemptAnswer $answer): array => $this->questionData($answer))
                ->values()
                ->all();
        }

        if ($attempt->status !== 'in_progress') {
            $data['result'] = $this->resultData($attempt);
        }

        return $data;
    }

    private function questionData(AttemptAnswer $answer, bool $includeNavigation = false): array
    {
        $data = [
            'id' => $answer->id,
            'sequence' => $answer->sequence,
            'question_text' => $answer->question_text,
            'options' => $answer->options,
            'points' => $answer->points,
            'selected_option' => $answer->selected_option,
        ];

        if ($includeNavigation) {
            $data['previous_question_id'] = AttemptAnswer::query()
                ->where('attempt_id', $answer->attempt_id)
                ->where('sequence', '<', $answer->sequence)
                ->orderByDesc('sequence')
                ->value('id');
            $data['next_question_id'] = AttemptAnswer::query()
                ->where('attempt_id', $answer->attempt_id)
                ->where('sequence', '>', $answer->sequence)
                ->orderBy('sequence')
                ->value('id');
        }

        return $data;
    }

    private function resultData(ExamAttempt $attempt): array
    {
        return [
            'correct_count' => $attempt->correct_count,
            'incorrect_count' => $attempt->incorrect_count,
            'unanswered_count' => $attempt->unanswered_count,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'duration_seconds' => $attempt->duration_seconds,
        ];
    }

    private function secondsRemaining(ExamAttempt $attempt): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($attempt->expires_at, false)));
    }
}
