<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $subjects = Subject::query()
            ->withCount(['questions', 'tests'])
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->when(
                ($validated['status'] ?? 'all') === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                ($validated['status'] ?? 'all') === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => collect($subjects->items())->map(fn (Subject $subject): array => $this->subjectData($subject))->values(),
            'meta' => [
                'total' => $subjects->total(),
                'active' => Subject::query()->where('is_active', true)->count(),
                'inactive' => Subject::query()->where('is_active', false)->count(),
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
                'per_page' => $subjects->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $validated = $this->validateSubject($request);
        $validated['code'] = Str::upper($validated['code']);
        $subject = Subject::query()->create($validated);
        $subject->loadCount(['questions', 'tests']);

        return response()->json([
            'message' => 'Subject created successfully.',
            'subject' => $this->subjectData($subject),
        ], 201);
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        $this->authorizeStaff($request);
        $validated = $this->validateSubject($request, $subject);
        $validated['code'] = Str::upper($validated['code']);
        $subject->update($validated);
        $subject->loadCount(['questions', 'tests']);

        return response()->json([
            'message' => 'Subject updated successfully.',
            'subject' => $this->subjectData($subject),
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);
    }

    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subjects', 'name')->ignore($subject?->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function subjectData(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'is_active' => $subject->is_active,
            'questions_count' => $subject->questions_count,
            'tests_count' => $subject->tests_count,
            'created_at' => $subject->created_at?->toISOString(),
            'updated_at' => $subject->updated_at?->toISOString(),
        ];
    }
}
