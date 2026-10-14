<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * GET /api/settings/public — unauthenticated allowlist of public settings.
     */
    public function publicSettings(SettingService $settings): JsonResponse
    {
        return response()->json(['settings' => $settings->publicSettings()]);
    }

    /**
     * GET /api/admin/settings — full operational view for admins.
     */
    public function adminIndex(Request $request, SettingService $settings): JsonResponse
    {
        $this->authorizeAdmin($request);

        $index = $settings->adminIndex();

        return response()->json([
            'settings' => $index,
            'groups' => array_values(array_unique(array_column($index, 'group'))),
        ]);
    }

    /**
     * PATCH /api/admin/settings — validate and update a partial settings map
     * with an audit reason. Payload:
     * { "settings": { "key": value, ... }, "reason": "why" }
     */
    public function adminUpdate(Request $request, SettingService $settings): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1', 'max:50'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $changed = $settings->update($validated['settings'], $request->user(), trim($validated['reason']));

        return response()->json([
            'message' => $changed === []
                ? 'No settings changed.'
                : 'Settings updated.',
            'changed' => collect($changed)
                ->map(fn (array $change, string $key): array => [
                    'key' => $key,
                    'old' => $change['old'],
                    'new' => $change['new'],
                ])
                ->values(),
            'settings' => $settings->adminIndex(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
