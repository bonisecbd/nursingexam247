<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $subjects = Subject::query()
            ->where('is_active', true)
            ->when(
                $validated['search'] ?? null,
                fn ($query, string $search) => $query->where(
                    fn ($query) => $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                ),
            )
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($subjects);
    }

    public function show(Subject $subject): JsonResponse
    {
        abort_unless($subject->is_active, 404);

        return response()->json(['subject' => $subject]);
    }
}
