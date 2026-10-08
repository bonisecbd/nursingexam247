<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ModelTestController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Topics, Test Unlock, and Progress/Analytics routes
 |--------------------------------------------------------------------------
 |
 | Registered as a second API route file from bootstrap/app.php so this
 | module can evolve independently of routes/api.php. All routes receive the
 | standard `api` middleware group and the /api prefix.
 |
 | Docs: docs/API.md (Topics, Test unlock, Analytics) and
 | docs/MODULES.md section 18 (Progress/Analytics).
 |
 */

// Topics: read is public (mirrors GET /api/subjects); write requires an
// authenticated admin/editor, enforced inside the controller.
Route::get('/topics', [TopicController::class, 'index']);

Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::post('/topics', [TopicController::class, 'store']);
    Route::patch('/topics/{topic}', [TopicController::class, 'update']);

    // Sequential test unlock: 02 requires completed 01, 03 requires 02, ...
    Route::get('/tests/{test}/unlock-status', [ModelTestController::class, 'unlockStatus']);

    // Progress/Analytics: server-derived, own attempts only.
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/subjects', [AnalyticsController::class, 'subjects']);
    Route::get('/analytics/topics', [AnalyticsController::class, 'topics']);
});
