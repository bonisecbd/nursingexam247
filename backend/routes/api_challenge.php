<?php

use App\Http\Controllers\Api\AdminChallengeController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Challenge Module
|--------------------------------------------------------------------------
| Wave 2 module routes. Register this module's endpoints in this file only.
| Do not edit routes/api.php, bootstrap/app.php, or other modules' files.
|
| All routes receive the standard `api` middleware group and the /api prefix.
| Docs: docs/MODULES.md section 16 (Challenge).
|
| Challenge attempts are normal Exam module attempts: answer-save, resume,
| and results are served by the existing /api/attempts endpoints. Only the
| challenge-specific surface (windows, limits, standings, settlement) lives
| here.
*/

// Public reads, mirroring the public test catalogue:
// list currently available challenges, safe details, public standings.
Route::get('/challenges', [ChallengeController::class, 'index']);
Route::get('/challenges/{challenge}', [ChallengeController::class, 'show']);
Route::get('/challenges/{challenge}/leaderboard', [ChallengeController::class, 'leaderboard']);

// Authenticated student actions: personal state, start/resume, and
// idempotent submission (delegates to the Exam module submit contract).
Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::get('/challenges/{challenge}/me', [ChallengeController::class, 'me']);
    Route::post('/challenges/{challenge}/attempts', [ChallengeController::class, 'start']);
    Route::post('/challenges/{challenge}/attempts/{attempt}/submit', [ChallengeController::class, 'submitAttempt'])
        ->middleware('throttle:10,1');
});

// Admin management; the `admin` role is enforced inside the controller,
// matching the other /api/admin endpoints.
Route::middleware(AuthenticateApiToken::class)->prefix('admin')->group(function (): void {
    Route::get('/challenges', [AdminChallengeController::class, 'index']);
    Route::post('/challenges', [AdminChallengeController::class, 'store']);
    Route::patch('/challenges/{challenge}', [AdminChallengeController::class, 'update']);
});
