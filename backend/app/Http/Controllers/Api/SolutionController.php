<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolutionController extends Controller
{
    public function index(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->ownedFinishedAttempt($request, $attempt);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $solutions = $attempt->answers()
            ->paginate($validated['per_page'] ?? 15)
            ->through(fn (AttemptAnswer $answer): array => $this->solutionData($answer));

        return response()->json($solutions);
    }

    public function show(Request $request, int $attempt, int $question): JsonResponse
    {
        $attempt = $this->ownedFinishedAttempt($request, $attempt);
        $answer = $attempt->answers()
            ->whereKey($question)
            ->firstOrFail();

        return response()->json([
            'solution' => $this->solutionData($answer),
        ]);
    }

    private function ownedFinishedAttempt(Request $request, int $attemptId): ExamAttempt
    {
        $attempt = ExamAttempt::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($attemptId);

        abort_unless(
            in_array($attempt->status, ['submitted', 'expired'], true),
            409,
            'Solutions are not available until the attempt is submitted or expires.',
        );

        return $attempt;
    }

    private function solutionData(AttemptAnswer $answer): array
    {
        return [
            'id' => $answer->id,
            'question_id' => $answer->question_id,
            'sequence' => $answer->sequence,
            'question_text' => $answer->question_text,
            'options' => $answer->options,
            'selected_option' => $answer->selected_option,
            'correct_option' => $answer->correct_option,
            'is_correct' => $answer->selected_option === null
                ? null
                : $answer->selected_option === $answer->correct_option,
            'explanation' => $answer->explanation,
            'points' => $answer->points,
        ];
    }
}
