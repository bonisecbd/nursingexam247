<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private const FINALIZED_STATUSES = ['submitted', 'expired'];

    /** Minimum answered questions in a topic before it can be flagged weak. */
    private const MIN_WEAK_SAMPLE = 5;

    /**
     * GET /api/analytics/overview
     *
     * Personal progress summary for the authenticated student. Every value is
     * derived from this user's finalized attempts on the server; the client
     * never supplies scores.
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $publishedTestCount = ModelTest::query()->where('status', 'published')->count();

        $totals = DB::table('attempts')
            ->where('user_id', $userId)
            ->whereIn('status', self::FINALIZED_STATUSES)
            ->selectRaw('COUNT(*) as attempt_count')
            ->selectRaw('COUNT(DISTINCT test_id) as completed_test_count')
            ->selectRaw('COALESCE(AVG(percentage), 0) as average_percentage')
            ->selectRaw('COALESCE(SUM(correct_count), 0) as correct_count')
            ->selectRaw('COALESCE(SUM(incorrect_count), 0) as incorrect_count')
            ->selectRaw('COALESCE(SUM(unanswered_count), 0) as unanswered_count')
            ->first();

        $lastAttempt = DB::table('attempts')
            ->where('user_id', $userId)
            ->whereIn('status', self::FINALIZED_STATUSES)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first(['percentage', 'finished_at']);

        $startedCount = DB::table('attempts')->where('user_id', $userId)->count();

        $correct = (int) $totals->correct_count;
        $incorrect = (int) $totals->incorrect_count;
        $answered = $correct + $incorrect;
        $completedTestCount = (int) $totals->completed_test_count;

        return response()->json([
            'total_tests' => $publishedTestCount,
            'last_score' => $lastAttempt !== null ? round((float) $lastAttempt->percentage, 2) : null,
            'average_score' => round((float) $totals->average_percentage, 2),
            'accuracy_rate' => $answered > 0 ? round($correct / $answered * 100, 2) : 0.0,
            'test_count' => $completedTestCount,
            'completion_rate' => $publishedTestCount > 0
                ? round($completedTestCount / $publishedTestCount * 100, 2)
                : 0.0,
            'attempt_count' => (int) $totals->attempt_count,
            'in_progress_count' => max(0, $startedCount - (int) $totals->attempt_count),
        ]);
    }

    /**
     * GET /api/analytics/subjects
     *
     * Per-subject performance across the student's finalized attempts.
     */
    public function subjects(Request $request): JsonResponse
    {
        $rows = DB::table('attempts')
            ->where('attempts.user_id', $request->user()->id)
            ->whereIn('attempts.status', self::FINALIZED_STATUSES)
            ->join('tests', 'tests.id', '=', 'attempts.test_id')
            ->join('subjects', 'subjects.id', '=', 'tests.subject_id')
            ->groupBy('subjects.id', 'subjects.name', 'subjects.code')
            ->selectRaw('subjects.id as subject_id')
            ->selectRaw('subjects.name as subject_name')
            ->selectRaw('subjects.code as subject_code')
            ->selectRaw('COUNT(*) as attempt_count')
            ->selectRaw('COUNT(DISTINCT attempts.test_id) as test_count')
            ->selectRaw('COALESCE(SUM(attempts.correct_count), 0) as correct_count')
            ->selectRaw('COALESCE(SUM(attempts.incorrect_count), 0) as incorrect_count')
            ->selectRaw('COALESCE(SUM(attempts.unanswered_count), 0) as unanswered_count')
            ->selectRaw('AVG(attempts.percentage) as average_percentage')
            ->selectRaw('MAX(attempts.percentage) as best_percentage')
            ->selectRaw('SUM(CASE WHEN attempts.passed = 1 THEN 1 ELSE 0 END) as passed_count')
            ->orderBy('subject_name')
            ->get();

        $data = $rows->map(function (object $row): array {
            $attemptCount = (int) $row->attempt_count;
            $correct = (int) $row->correct_count;
            $incorrect = (int) $row->incorrect_count;
            $answered = $correct + $incorrect;

            return [
                'subject_id' => (int) $row->subject_id,
                'subject_name' => $row->subject_name,
                'subject_code' => $row->subject_code,
                'attempt_count' => $attemptCount,
                'test_count' => (int) $row->test_count,
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
                'unanswered_count' => (int) $row->unanswered_count,
                'average_percentage' => round((float) $row->average_percentage, 2),
                'best_percentage' => round((float) $row->best_percentage, 2),
                'pass_rate' => $attemptCount > 0
                    ? round((int) $row->passed_count / $attemptCount * 100, 2)
                    : 0.0,
                'accuracy_rate' => $answered > 0 ? round($correct / $answered * 100, 2) : 0.0,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'subject_count' => $data->count(),
            ],
        ]);
    }

    /**
     * GET /api/analytics/topics
     *
     * Per-topic accuracy built from saved answer snapshots joined back to the
     * question's topic. Weakest topics are returned first so the dashboard can
     * surface study recommendations.
     */
    public function topics(Request $request): JsonResponse
    {
        $rows = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->join('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->where('attempts.user_id', $request->user()->id)
            ->whereIn('attempts.status', self::FINALIZED_STATUSES)
            ->whereNotNull('attempt_answers.question_id')
            ->groupBy('topics.id', 'topics.name', 'topics.subject_id', 'subjects.name')
            ->selectRaw('topics.id as topic_id')
            ->selectRaw('topics.name as topic_name')
            ->selectRaw('topics.subject_id as subject_id')
            ->selectRaw('subjects.name as subject_name')
            ->selectRaw('COUNT(*) as answered_count')
            ->selectRaw('SUM(CASE WHEN attempt_answers.selected_option IS NULL THEN 1 ELSE 0 END) as skipped_count')
            ->selectRaw('SUM(CASE WHEN attempt_answers.selected_option = attempt_answers.correct_option THEN 1 ELSE 0 END) as correct_count')
            ->orderByDesc('answered_count')
            ->limit(100)
            ->get();

        $data = $rows->map(function (object $row): array {
            $answered = (int) $row->answered_count;
            $correct = (int) $row->correct_count;
            $skipped = (int) $row->skipped_count;
            $attempted = $answered - $skipped;
            $accuracy = $attempted > 0 ? round($correct / $attempted * 100, 2) : 0.0;

            return [
                'topic_id' => (int) $row->topic_id,
                'topic_name' => $row->topic_name,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => $row->subject_name,
                'answered_count' => $answered,
                'attempted_count' => $attempted,
                'skipped_count' => $skipped,
                'correct_count' => $correct,
                'incorrect_count' => max(0, $attempted - $correct),
                'accuracy_rate' => $accuracy,
                // A topic is only flagged weak with enough evidence: the
                // sample must reach MIN_WEAK_SAMPLE before the threshold applies.
                'is_weak' => $answered >= self::MIN_WEAK_SAMPLE && $accuracy <= 50,
            ];
        })->sortBy('accuracy_rate')->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'topic_count' => $data->count(),
                'weak_threshold' => 50,
                'min_sample' => self::MIN_WEAK_SAMPLE,
            ],
        ]);
    }
}
