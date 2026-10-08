<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeContentManagement($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $questions = Question::query()
            ->with('subject:id,name,code')
            ->withCount('tests')
            ->withCount(['tests as published_tests_count' => fn ($query) => $query->where('tests.status', 'published')])
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where(
                'question_text',
                'like',
                '%'.$search.'%',
            ))
            ->when($validated['subject_id'] ?? null, fn ($query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($validated['difficulty'] ?? null, fn ($query, string $difficulty) => $query->where('difficulty', $difficulty))
            ->when(($validated['status'] ?? 'all') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? 'all') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('updated_at')
            ->paginate($validated['per_page'] ?? 15);

        $counts = Question::query()
            ->select('is_active', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return response()->json([
            'data' => collect($questions->items())->map(fn (Question $question): array => $this->questionData($question))->values(),
            'meta' => [
                'total' => $questions->total(),
                'active' => (int) ($counts[1] ?? $counts['1'] ?? 0),
                'inactive' => (int) ($counts[0] ?? $counts['0'] ?? 0),
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeContentManagement($request);
        $validated = $this->validateQuestion($request);
        $this->validateCorrectOption($validated);
        $this->ensureActiveSubject($validated['subject_id']);

        $question = Question::query()->create($validated + [
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $question->load('subject:id,name,code')->loadCount('tests')
            ->loadCount(['tests as published_tests_count' => fn ($query) => $query->where('tests.status', 'published')]);

        return response()->json([
            'message' => 'Question created successfully.',
            'question' => $this->questionData($question),
        ], 201);
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        $this->authorizeContentManagement($request);

        if ($question->tests()->where('tests.status', 'published')->exists()) {
            throw ValidationException::withMessages([
                'question' => ['Questions assigned to published tests cannot be edited or deactivated. Create a new question for future tests.'],
            ]);
        }

        $validated = $this->validateQuestion($request, partial: true);
        $this->validateCorrectOption($validated, $question);
        $subjectId = $validated['subject_id'] ?? $question->subject_id;
        $this->ensureActiveSubject($subjectId, $question);
        $question->update($validated);
        $question->load('subject:id,name,code')->loadCount('tests')
            ->loadCount(['tests as published_tests_count' => fn ($query) => $query->where('tests.status', 'published')]);

        return response()->json([
            'message' => 'Question updated successfully.',
            'question' => $this->questionData($question),
        ]);
    }

    private function authorizeContentManagement(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);
    }

    private function validateQuestion(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'subject_id' => [$required, 'integer', 'exists:subjects,id'],
            'question_text' => [$required, 'string', 'max:10000'],
            'options' => [$required, 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:1000'],
            'correct_option' => [$required, 'integer', 'min:1', 'max:6'],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'difficulty' => [$required, Rule::in(['easy', 'medium', 'hard'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateCorrectOption(array $validated, ?Question $question = null): void
    {
        $options = $validated['options'] ?? $question?->options;
        $correctOption = $validated['correct_option'] ?? $question?->correct_option;

        if ($options !== null && $correctOption !== null && $correctOption > count($options)) {
            throw ValidationException::withMessages([
                'correct_option' => ['The correct option must refer to one of the supplied options.'],
            ]);
        }
    }

    private function ensureActiveSubject(int $subjectId, ?Question $question = null): void
    {
        $subject = DB::table('subjects')->where('id', $subjectId)->first();

        if (! $subject || (! $subject->is_active && $question?->subject_id !== $subjectId)) {
            throw ValidationException::withMessages([
                'subject_id' => ['Questions must belong to an active subject.'],
            ]);
        }
    }

    private function questionData(Question $question): array
    {
        return [
            'id' => $question->id,
            'subject' => $question->subject ? [
                'id' => $question->subject->id,
                'name' => $question->subject->name,
                'code' => $question->subject->code,
            ] : null,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'correct_option' => $question->correct_option,
            'explanation' => $question->explanation,
            'difficulty' => $question->difficulty,
            'is_active' => $question->is_active,
            'tests_count' => $question->tests_count,
            'published_tests_count' => $question->published_tests_count,
            'created_at' => $question->created_at?->toISOString(),
            'updated_at' => $question->updated_at?->toISOString(),
        ];
    }
}
