<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'blocked'])],
            'sort' => ['nullable', Rule::in(['newest', 'activity'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $students = User::query()->where('role', 'student');
        $statusCounts = (clone $students)
            ->select('is_active', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        $query = (clone $students)
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'is_active',
                'created_at',
            ])
            ->withCount('attempts')
            ->withMax('apiTokens', 'last_used_at');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (($validated['status'] ?? 'all') === 'active') {
            $query->where('is_active', true);
        } elseif (($validated['status'] ?? 'all') === 'blocked') {
            $query->where('is_active', false);
        }

        if (($validated['sort'] ?? 'newest') === 'activity') {
            $query->orderByDesc('api_tokens_max_last_used_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toISOString(),
                'last_activity_at' => $user->api_tokens_max_last_used_at,
                'attempts_count' => $user->attempts_count,
            ])->values(),
            'meta' => [
                'total' => $users->total(),
                'active' => (int) ($statusCounts[1] ?? $statusCounts['1'] ?? 0),
                'blocked' => (int) ($statusCounts[0] ?? $statusCounts['0'] ?? 0),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($user->role === 'student', 404);

        $user->loadCount('attempts');

        $attempts = ExamAttempt::query()
            ->with('test:id,title,code')
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->limit(10)
            ->get([
                'id',
                'test_id',
                'status',
                'started_at',
                'finished_at',
                'score',
                'total_marks',
                'percentage',
                'correct_count',
                'incorrect_count',
                'unanswered_count',
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
                'gender' => $user->gender,
                'address' => $user->address,
                'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toISOString(),
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'attempts_count' => $user->attempts_count,
            ],
            'activity' => [
                'total_attempts' => $user->attempts_count,
                'completed_attempts' => ExamAttempt::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['submitted', 'expired'])
                    ->count(),
                'average_score' => ExamAttempt::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['submitted', 'expired'])
                    ->avg('percentage'),
                'recent_attempts' => $attempts->map(fn (ExamAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'test' => $attempt->test ? [
                        'title' => $attempt->test->title,
                        'code' => $attempt->test->code,
                    ] : null,
                    'status' => $attempt->status,
                    'started_at' => $attempt->started_at?->toISOString(),
                    'finished_at' => $attempt->finished_at?->toISOString(),
                    'score' => $attempt->score,
                    'total_marks' => $attempt->total_marks,
                    'percentage' => $attempt->percentage,
                    'correct_count' => $attempt->correct_count,
                    'incorrect_count' => $attempt->incorrect_count,
                    'unanswered_count' => $attempt->unanswered_count,
                ])->values(),
            ],
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($user->role === 'student', 404);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $user->update(['is_active' => $validated['is_active']]);

            if (! $validated['is_active']) {
                $user->apiTokens()->update(['expires_at' => now()]);
            }
        });

        return response()->json([
            'message' => $validated['is_active']
                ? 'Student account activated successfully.'
                : 'Student account blocked and active sessions revoked.',
            'user' => [
                'id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
