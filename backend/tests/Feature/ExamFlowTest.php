<?php

namespace Tests\Feature;

use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_marking_is_applied_to_incorrect_answers(): void
    {
        $service = new ExamService;
        $result = $service->calculateResult([
            'correct' => 70,
            'incorrect' => 10,
            'unanswered' => 20,
        ], 100, -0.25);

        $this->assertEquals(67.5, $result['score']);
        $this->assertEquals(67.5, $result['percentage']);
        $this->assertFalse($result['passed']);
    }

    public function test_auto_generated_model_test_has_required_size_and_rules(): void
    {
        $service = new ExamService;
        $exam = $service->buildModelTest(
            'Nursing Model Test 01',
            100,
            60,
            100,
            [
                'Nursing Fundamentals' => 8,
                'Anatomy & Physiology' => 8,
                'Medical-Surgical Nursing' => 8,
                'Pharmacology' => 6,
                'Community Health Nursing' => 6,
                'Child Health Nursing' => 6,
                'Maternal & Child Health' => 6,
                'Psychiatric Nursing' => 6,
                'Nutrition' => 6,
                'General Knowledge' => 6,
                'Bangladesh Affairs' => 6,
                'English' => 6,
                'Mathematics' => 6,
                'ICT' => 6,
                'Previous Nursing Questions' => 10,
            ]
        );

        $this->assertEquals(100, $exam['question_count']);
        $this->assertEquals(100, $exam['total_marks']);
        $this->assertEquals(60, $exam['duration_minutes']);
        $this->assertEquals(-0.25, $exam['negative_marking']);
        $this->assertEquals(100, array_sum($exam['subject_counts']));
    }
}
