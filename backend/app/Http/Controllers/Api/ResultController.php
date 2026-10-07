<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_id' => ['sometimes', 'integer', 'exists:tests,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $attempts = ExamAttempt::query()
            ->with('test:id,title,code,subject_id')
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['submitted', 'expired'])
            ->when($validated['test_id'] ?? null, function ($query, int $testId): void {
                $query->where('test_id', $testId);
            })
            ->orderByDesc('finished_at')
            ->paginate($validated['per_page'] ?? 15);

        $attempts->getCollection()->transform(
            fn (ExamAttempt $attempt): array => $this->resultData($attempt, includeTest: true),
        );

        return response()->json($attempts);
    }

    public function show(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->ownedFinishedAttempt($request, $attempt);

        return response()->json([
            'result' => $this->resultData($attempt, includeTest: true),
        ]);
    }

    public function summary(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->ownedFinishedAttempt($request, $attempt);

        return response()->json([
            'result' => $this->resultData($attempt),
        ]);
    }

    private function ownedFinishedAttempt(Request $request, int $attemptId): ExamAttempt
    {
        $attempt = ExamAttempt::query()
            ->where('user_id', $request->user()->id)
            ->with('test:id,title,code,subject_id')
            ->findOrFail($attemptId);

        abort_unless(
            in_array($attempt->status, ['submitted', 'expired'], true),
            409,
            'The result is not available until the attempt is submitted or expires.',
        );

        return $attempt;
    }

    private function resultData(ExamAttempt $attempt, bool $includeTest = false): array
    {
        $data = [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status,
            'correct_count' => $attempt->correct_count,
            'wrong_count' => $attempt->incorrect_count,
            'skipped_count' => $attempt->unanswered_count,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'started_at' => $attempt->started_at->toISOString(),
            'finished_at' => $attempt->finished_at?->toISOString(),
            'duration_seconds' => $attempt->duration_seconds,
        ];

        if ($includeTest) {
            $data['test'] = $attempt->test ? [
                'id' => $attempt->test->id,
                'title' => $attempt->test->title,
                'code' => $attempt->test->code,
                'subject_id' => $attempt->test->subject_id,
            ] : null;
        }

        return $data;
    }
}
