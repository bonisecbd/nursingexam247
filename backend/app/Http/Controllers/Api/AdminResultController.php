<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeResultAccess($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'test_id' => ['nullable', 'integer', 'exists:tests,id'],
            'status' => ['nullable', Rule::in(['all', 'submitted', 'expired', 'passed', 'failed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $results = $this->finishedStudentAttempts()
            ->with([
                'user:id,name',
                'test:id,title,code,subject_id',
                'test.subject:id,name',
            ])
            ->when($validated['subject_id'] ?? null, fn ($query, int $subjectId) => $query->whereHas(
                'test',
                fn ($testQuery) => $testQuery->where('subject_id', $subjectId),
            ))
            ->when($validated['test_id'] ?? null, fn ($query, int $testId) => $query->where('test_id', $testId))
            ->when(($validated['status'] ?? 'all') === 'passed', fn ($query) => $query->where('passed', true))
            ->when(($validated['status'] ?? 'all') === 'failed', fn ($query) => $query->where('passed', false))
            ->when(
                isset($validated['status']) && in_array($validated['status'], ['submitted', 'expired'], true),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('test', function ($testQuery) use ($search): void {
                            $testQuery->where('title', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('finished_at')
            ->paginate($validated['per_page'] ?? 15);

        $summary = $this->finishedStudentAttempts();
        $finishedCount = (clone $summary)->count();
        $passedCount = (clone $summary)->where('passed', true)->count();

        return response()->json([
            'data' => collect($results->items())->map(fn (ExamAttempt $attempt): array => $this->resultData($attempt))->values(),
            'meta' => [
                'total' => $results->total(),
                'passed' => (int) (clone $summary)->where('passed', true)->count(),
                'failed' => (int) (clone $summary)->where('passed', false)->count(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
            ],
            'analytics' => [
                'completed_attempts' => $finishedCount,
                'average_percentage' => (clone $summary)->avg('percentage'),
                'pass_rate' => $finishedCount > 0 ? round(($passedCount / $finishedCount) * 100, 2) : null,
                'subjects' => $this->subjectPerformance(),
                'tests' => $this->testPerformance(),
            ],
        ]);
    }

    private function authorizeResultAccess(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);
    }

    private function finishedStudentAttempts()
    {
        return ExamAttempt::query()
            ->whereIn('status', ['submitted', 'expired'])
            ->whereHas('user', fn ($query) => $query->where('role', 'student'));
    }

    private function subjectPerformance(): array
    {
        return DB::table('attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->join('tests', 'tests.id', '=', 'attempts.test_id')
            ->join('subjects', 'subjects.id', '=', 'tests.subject_id')
            ->where('users.role', 'student')
            ->whereIn('attempts.status', ['submitted', 'expired'])
            ->select(
                'subjects.id',
                'subjects.name',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('AVG(attempts.percentage) as average_percentage'),
                DB::raw('SUM(CASE WHEN attempts.passed = 1 THEN 1 ELSE 0 END) as passed'),
            )
            ->groupBy('subjects.id', 'subjects.name')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'attempts' => (int) $row->attempts,
                'average_percentage' => round((float) $row->average_percentage, 2),
                'pass_rate' => round(((int) $row->passed / (int) $row->attempts) * 100, 2),
            ])
            ->all();
    }

    private function testPerformance(): array
    {
        return DB::table('attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->join('tests', 'tests.id', '=', 'attempts.test_id')
            ->where('users.role', 'student')
            ->whereIn('attempts.status', ['submitted', 'expired'])
            ->select(
                'tests.id',
                'tests.title',
                'tests.code',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('AVG(attempts.percentage) as average_percentage'),
                DB::raw('SUM(CASE WHEN attempts.passed = 1 THEN 1 ELSE 0 END) as passed'),
            )
            ->groupBy('tests.id', 'tests.title', 'tests.code')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'code' => $row->code,
                'attempts' => (int) $row->attempts,
                'average_percentage' => round((float) $row->average_percentage, 2),
                'pass_rate' => round(((int) $row->passed / (int) $row->attempts) * 100, 2),
            ])
            ->all();
    }

    private function resultData(ExamAttempt $attempt): array
    {
        return [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status,
            'student' => $attempt->user ? [
                'id' => $attempt->user->id,
                'name' => $attempt->user->name,
            ] : null,
            'test' => $attempt->test ? [
                'id' => $attempt->test->id,
                'title' => $attempt->test->title,
                'code' => $attempt->test->code,
                'subject' => $attempt->test->subject?->name,
            ] : null,
            'correct_count' => $attempt->correct_count,
            'wrong_count' => $attempt->incorrect_count,
            'skipped_count' => $attempt->unanswered_count,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'finished_at' => $attempt->finished_at?->toISOString(),
            'duration_seconds' => $attempt->duration_seconds,
        ];
    }
}
