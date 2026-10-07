<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);

        $students = User::query()->where('role', 'student');
        $totalAttempts = ExamAttempt::query()->count();
        $finishedAttempts = ExamAttempt::query()->whereIn('status', ['submitted', 'expired']);
        $attemptsByDay = ExamAttempt::query()
            ->where('started_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(started_at) as activity_date, COUNT(*) as attempt_count')
            ->groupByRaw('DATE(started_at)')
            ->pluck('attempt_count', 'activity_date');

        $activity = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($attemptsByDay): array {
                $date = now()->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'day' => now()->subDays($daysAgo)->format('D'),
                    'attempts' => (int) ($attemptsByDay[$date] ?? 0),
                ];
            })
            ->values();

        $publishedTests = ModelTest::query()->where('status', 'published')->count();
        $activeSubjects = Subject::query()->where('is_active', true)->count();

        return response()->json([
            'metrics' => [
                'students' => $students->count(),
                'new_students_today' => (clone $students)->whereDate('created_at', today())->count(),
                'active_students' => (clone $students)->where('is_active', true)->count(),
                'questions' => Question::query()->count(),
                'tests' => ModelTest::query()->count(),
                'published_tests' => $publishedTests,
                'draft_tests' => ModelTest::query()->where('status', 'draft')->count(),
                'active_subjects' => $activeSubjects,
                'attempts_today' => ExamAttempt::query()->whereDate('started_at', today())->count(),
                'finished_attempts' => $finishedAttempts->count(),
                'average_score' => (clone $finishedAttempts)->avg('percentage'),
                'completion_rate' => $totalAttempts > 0
                    ? round(($finishedAttempts->count() / $totalAttempts) * 100, 2)
                    : null,
            ],
            'exam_activity' => $activity,
            'unavailable_metrics' => ['revenue', 'premium_users', 'referrals'],
            'generated_at' => now()->toISOString(),
        ]);
    }
}
