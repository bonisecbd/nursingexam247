<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeExamMonitoring($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'in_progress', 'submitted', 'expired', 'completed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $attempts = ExamAttempt::query()
            ->with([
                'user:id,name,is_active',
                'test:id,title,code,subject_id',
                'test.subject:id,name',
            ])
            ->withCount('answers')
            ->withCount(['answers as answered_count' => fn ($query) => $query->whereNotNull('selected_option')])
            ->whereHas('user', fn ($query) => $query->where('role', 'student'))
            ->when(($validated['status'] ?? 'all') === 'completed', fn ($query) => $query->whereIn('status', ['submitted', 'expired']))
            ->when(
                isset($validated['status']) && ! in_array($validated['status'], ['all', 'completed'], true),
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
            ->orderByDesc('started_at')
            ->paginate($validated['per_page'] ?? 15);

        $counts = DB::table('attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->where('users.role', 'student')
            ->select('attempts.status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('attempts.status')
            ->pluck('aggregate', 'attempts.status');

        return response()->json([
            'data' => collect($attempts->items())->map(fn (ExamAttempt $attempt): array => $this->attemptData($attempt))->values(),
            'meta' => [
                'total' => $attempts->total(),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'submitted' => (int) ($counts['submitted'] ?? 0),
                'expired' => (int) ($counts['expired'] ?? 0),
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'per_page' => $attempts->perPage(),
            ],
        ]);
    }

    public function show(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->authorizeExamMonitoring($request);

        abort_unless($attempt->user()->where('role', 'student')->exists(), 404);

        $attempt->load([
            'user:id,name,is_active',
            'test:id,title,code,subject_id',
            'test.subject:id,name',
        ]);
        $attempt->loadCount('answers');
        $attempt->loadCount(['answers as answered_count' => fn ($query) => $query->whereNotNull('selected_option')]);

        return response()->json(['attempt' => $this->attemptData($attempt)]);
    }

    private function authorizeExamMonitoring(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);
    }

    private function attemptData(ExamAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'student' => $attempt->user ? [
                'id' => $attempt->user->id,
                'name' => $attempt->user->name,
                'is_active' => $attempt->user->is_active,
            ] : null,
            'test' => $attempt->test ? [
                'id' => $attempt->test->id,
                'title' => $attempt->test->title,
                'code' => $attempt->test->code,
                'subject' => $attempt->test->subject?->name,
            ] : null,
            'started_at' => $attempt->started_at?->toISOString(),
            'expires_at' => $attempt->expires_at?->toISOString(),
            'finished_at' => $attempt->finished_at?->toISOString(),
            'duration_seconds' => $attempt->duration_seconds,
            'answered_count' => $attempt->answered_count,
            'question_count' => $attempt->answers_count,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
            'correct_count' => $attempt->correct_count,
            'incorrect_count' => $attempt->incorrect_count,
            'unanswered_count' => $attempt->unanswered_count,
        ];
    }
}
