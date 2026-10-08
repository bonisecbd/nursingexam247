<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    /**
     * GET /api/topics?subject_id={id}
     *
     * Lists active topics, optionally filtered by subject. Students use this
     * for question/analytics filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $topics = Topic::query()
            ->with('subject:id,name,code')
            ->where('is_active', true)
            ->when($validated['subject_id'] ?? null, fn ($query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when(
                $validated['search'] ?? null,
                fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'),
            )
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($topics);
    }

    /**
     * POST /api/topics
     *
     * Admins and editors create topics inside an active subject.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $isActive = DB::table('subjects')
            ->where('id', $validated['subject_id'])
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'subject_id' => ['Topics must belong to an active subject.'],
            ]);
        }

        $duplicate = Topic::query()
            ->where('subject_id', $validated['subject_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => ['A topic with this name already exists in the selected subject.'],
            ]);
        }

        $topic = Topic::query()->create($validated + [
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Topic created successfully.',
            'topic' => $topic->load('subject:id,name,code'),
        ], 201);
    }

    /**
     * PATCH /api/topics/{topic}
     *
     * Admins and editors rename or activate/deactivate a topic. Deleting is
     * intentionally unsupported; questions keep their topic history.
     */
    public function update(Request $request, Topic $topic): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'editor'], true), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $duplicate = Topic::query()
                ->where('subject_id', $topic->subject_id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $topic->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => ['A topic with this name already exists in the selected subject.'],
                ]);
            }
        }

        $topic->update($validated);

        return response()->json([
            'message' => 'Topic updated successfully.',
            'topic' => $topic->load('subject:id,name,code'),
        ]);
    }
}
