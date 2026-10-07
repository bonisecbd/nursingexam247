<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelTest;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ModelTestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tests = ModelTest::query()
            ->with('subject:id,name,code')
            ->where('status', 'published')
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where('title', 'like', '%'.$search.'%');
            })
            ->when($validated['subject_id'] ?? null, function ($query, int $subjectId): void {
                $query->where('subject_id', $subjectId);
            })
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($tests);
    }

    public function show(ModelTest $test): JsonResponse
    {
        abort_unless($test->status === 'published', 404);
        abort_if($test->is_premium, 403, 'This premium test requires an active premium subscription.');

        $test->load([
            'subject:id,name,code',
            'questions:id,subject_id,question_text,options,difficulty',
        ]);

        return response()->json([
            'test' => $this->testData($test, includeQuestions: true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeContentManagement($request);

        $validated = $request->validate($this->testRules());
        $questionIds = $validated['question_ids'] ?? [];
        unset($validated['question_ids']);

        $this->validateSubject($validated['subject_id']);
        $this->validatePassingScore($validated['passing_score'], $validated['total_marks']);
        $this->validateQuestionSet($questionIds, $validated['subject_id']);

        $test = DB::transaction(function () use ($validated, $questionIds, $request): ModelTest {
            $test = ModelTest::query()->create($validated + [
                'code' => Str::upper(Str::random(12)),
                'is_negative_marking_enabled' => $validated['is_negative_marking_enabled'] ?? true,
                'is_premium' => $validated['is_premium'] ?? false,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncQuestions($test, $questionIds);

            return $test;
        });

        return response()->json([
            'message' => 'Draft test created successfully.',
            'test' => $this->testData($test->load('subject:id,name,code')),
        ], 201);
    }

    public function update(Request $request, ModelTest $test): JsonResponse
    {
        $this->authorizeContentManagement($request);
        $this->ensureDraft($test);

        $validated = $request->validate($this->testRules(partial: true));
        $subjectId = $validated['subject_id'] ?? $test->subject_id;
        $totalMarks = $validated['total_marks'] ?? $test->total_marks;
        $passingScore = $validated['passing_score'] ?? $test->passing_score;
        $this->validateSubject($subjectId);
        $this->validatePassingScore($passingScore, $totalMarks);
        $this->validateQuestionSet(
            $test->questions()->pluck('questions.id')->all(),
            $subjectId,
        );

        $test->update($validated);

        return response()->json([
            'message' => 'Draft test updated successfully.',
            'test' => $this->testData($test->fresh()->load('subject:id,name,code')),
        ]);
    }

    public function replaceQuestions(Request $request, ModelTest $test): JsonResponse
    {
        $this->authorizeContentManagement($request);
        $this->ensureDraft($test);

        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'max:500'],
            'question_ids.*' => ['required', 'integer', 'distinct', 'exists:questions,id'],
        ]);

        $this->validateQuestionSet($validated['question_ids'], $test->subject_id);
        $this->syncQuestions($test, $validated['question_ids']);

        return response()->json([
            'message' => 'Draft test questions updated successfully.',
            'assigned_question_count' => count($validated['question_ids']),
        ]);
    }

    public function publish(Request $request, ModelTest $test): JsonResponse
    {
        $this->authorizeContentManagement($request);
        $this->ensureDraft($test);

        $assignedQuestionCount = $test->questions()->count();

        if ($assignedQuestionCount !== $test->question_count) {
            throw ValidationException::withMessages([
                'question_ids' => ['The number of assigned questions must equal question_count before publishing.'],
            ]);
        }

        $points = (float) $test->questions()->sum('test_questions.points');

        if (abs($points - (float) $test->total_marks) > 0.001) {
            throw ValidationException::withMessages([
                'total_marks' => ['The assigned question points must equal total_marks before publishing.'],
            ]);
        }

        $this->validateQuestionSet(
            $test->questions()->pluck('questions.id')->all(),
            $test->subject_id,
        );
        $this->validateSubject($test->subject_id);

        $test->update(['status' => 'published']);

        return response()->json([
            'message' => 'Test published successfully.',
            'test' => $this->testData($test->fresh()->load('subject:id,name,code')),
        ]);
    }

    private function testRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'subject_id' => [$required, 'integer', 'exists:subjects,id'],
            'duration_minutes' => [$required, 'integer', 'min:1', 'max:600'],
            'question_count' => [$required, 'integer', 'min:1', 'max:500'],
            'total_marks' => [$required, 'numeric', 'gt:0', 'decimal:0,2'],
            'passing_score' => [$required, 'numeric', 'min:0', 'decimal:0,2'],
            'negative_marking' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'is_negative_marking_enabled' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'question_ids' => [$partial ? 'prohibited' : 'sometimes', 'array', 'max:500'],
            'question_ids.*' => ['required', 'integer', 'distinct', 'exists:questions,id'],
        ];
    }

    private function authorizeContentManagement(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);
    }

    private function ensureDraft(ModelTest $test): void
    {
        if ($test->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft tests can be edited or published.'],
            ]);
        }
    }

    private function validateQuestionSet(array $questionIds, int $subjectId): void
    {
        if ($questionIds === []) {
            return;
        }

        $validCount = Question::query()
            ->whereIn('id', $questionIds)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->count();

        if ($validCount !== count($questionIds)) {
            throw ValidationException::withMessages([
                'question_ids' => ['Every question must be active and belong to the selected subject.'],
            ]);
        }
    }

    private function validateSubject(int $subjectId): void
    {
        $isActive = DB::table('subjects')
            ->where('id', $subjectId)
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'subject_id' => ['The selected subject must be active.'],
            ]);
        }
    }

    private function validatePassingScore(int|float|string $passingScore, int|float|string $totalMarks): void
    {
        if ((float) $passingScore > (float) $totalMarks) {
            throw ValidationException::withMessages([
                'passing_score' => ['The passing score cannot exceed total_marks.'],
            ]);
        }
    }

    private function syncQuestions(ModelTest $test, array $questionIds): void
    {
        $totalCents = (int) round((float) $test->total_marks * 100);
        $baseCents = intdiv($totalCents, $test->question_count);
        $remainingCents = $totalCents % $test->question_count;
        $assignments = [];

        foreach (array_values($questionIds) as $sequence => $questionId) {
            $pointsCents = $baseCents + ($sequence < $remainingCents ? 1 : 0);
            $assignments[$questionId] = [
                'sequence' => $sequence + 1,
                'points' => number_format($pointsCents / 100, 2, '.', ''),
            ];
        }

        $test->questions()->sync($assignments);
    }

    private function testData(ModelTest $test, bool $includeQuestions = false): array
    {
        $data = [
            'id' => $test->id,
            'title' => $test->title,
            'code' => $test->code,
            'description' => $test->description,
            'subject' => $test->subject,
            'duration_minutes' => $test->duration_minutes,
            'question_count' => $test->question_count,
            'total_marks' => $test->total_marks,
            'passing_score' => $test->passing_score,
            'negative_marking' => $test->negative_marking,
            'is_negative_marking_enabled' => $test->is_negative_marking_enabled,
            'is_premium' => $test->is_premium,
            'status' => $test->status,
        ];

        if ($includeQuestions) {
            $data['questions'] = $test->questions->map(fn (Question $question): array => [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => $question->options,
                'sequence' => $question->pivot->sequence,
                'points' => $question->pivot->points,
                'difficulty' => $question->difficulty,
            ])->all();
        }

        return $data;
    }
}
