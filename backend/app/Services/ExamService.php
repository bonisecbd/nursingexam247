<?php

namespace App\Services;

class ExamService
{
    /**
     * Default negative marking applied per incorrect answer (as a mark delta).
     */
    public const DEFAULT_NEGATIVE_MARKING = -0.25;

    /**
     * Default percentage required to pass a test.
     */
    public const DEFAULT_PASSING_SCORE = 70.0;

    /**
     * Default passing score (in marks) for an auto-generated model test.
     */
    public const MODEL_TEST_PASSING_SCORE = 50;

    /**
     * Calculate the result of an exam attempt from raw answer counts.
     *
     * score = correct * 1 + incorrect * negativeMarking + unanswered * 0
     * percentage = (score / totalMarks) * 100
     *
     * @param  array{correct?: int, incorrect?: int, unanswered?: int}  $counts
     * @return array{correct: int, incorrect: int, unanswered: int, score: float, percentage: float, passed: bool}
     */
    public function calculateResult(
        array $counts,
        float $totalMarks,
        float $negativeMarking,
        float $passingScore = self::DEFAULT_PASSING_SCORE,
    ): array {
        if ($totalMarks <= 0) {
            throw new \InvalidArgumentException('Total marks must be greater than zero.');
        }

        $correct = max(0, (int) ($counts['correct'] ?? 0));
        $incorrect = max(0, (int) ($counts['incorrect'] ?? 0));
        $unanswered = max(0, (int) ($counts['unanswered'] ?? 0));

        $score = ($correct * 1.0) + ($incorrect * $negativeMarking) + ($unanswered * 0.0);
        $percentage = max(0.0, min(100.0, ($score / $totalMarks) * 100));

        return [
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unanswered' => $unanswered,
            'score' => (float) $score,
            'percentage' => (float) $percentage,
            'passed' => $percentage >= $passingScore,
        ];
    }

    /**
     * Build the definition of an auto-generated model test.
     *
     * Pure planning/calculation — no persistence involved.
     *
     * @param  array<string, int>  $subjectCounts  subject name => question count
     * @return array{
     *     title: string,
     *     question_count: int,
     *     total_marks: int,
     *     duration_minutes: int,
     *     negative_marking: float,
     *     is_negative_marking_enabled: bool,
     *     subject_counts: array<string, int>,
     *     passing_score: int
     * }
     *
     * @throws \InvalidArgumentException
     */
    public function buildModelTest(
        string $title,
        int $questionCount,
        int $durationMinutes,
        int $totalMarks,
        array $subjectCounts,
        float $negativeMarking = self::DEFAULT_NEGATIVE_MARKING,
        int $passingScore = self::MODEL_TEST_PASSING_SCORE,
    ): array {
        if ($questionCount <= 0) {
            throw new \InvalidArgumentException('Question count must be greater than zero.');
        }

        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException('Duration must be greater than zero minutes.');
        }

        if ($totalMarks <= 0) {
            throw new \InvalidArgumentException('Total marks must be greater than zero.');
        }

        if ($passingScore < 0) {
            throw new \InvalidArgumentException('Passing score cannot be negative.');
        }

        foreach ($subjectCounts as $subject => $count) {
            if (! is_int($count) || $count <= 0) {
                throw new \InvalidArgumentException(
                    'Subject "'.(string) $subject.'" must have a question count greater than zero.'
                );
            }
        }

        if (array_sum($subjectCounts) !== $questionCount) {
            throw new \InvalidArgumentException(sprintf(
                'Subject counts sum to %d but the test requires %d questions.',
                array_sum($subjectCounts),
                $questionCount,
            ));
        }

        return [
            'title' => $title,
            'question_count' => $questionCount,
            'total_marks' => $totalMarks,
            'duration_minutes' => $durationMinutes,
            'negative_marking' => $negativeMarking,
            'is_negative_marking_enabled' => $negativeMarking < 0,
            'subject_counts' => $subjectCounts,
            'passing_score' => $passingScore,
        ];
    }
}
