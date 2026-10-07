<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\ModelTestController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\SolutionController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'NurseExam247 API is running.',
        'endpoints' => [
            'register' => 'POST /api/auth/register',
            'login' => 'POST /api/auth/login',
            'forgot_password' => 'POST /api/auth/forgot-password',
            'reset_password' => 'POST /api/auth/reset-password',
            'profile' => 'GET|PATCH /api/profile',
            'subjects' => 'GET /api/subjects',
            'tests' => 'GET /api/tests',
            'admin_tests' => 'POST /api/admin/tests',
            'attempts' => 'POST /api/attempts',
            'attempt_history' => 'GET /api/attempts',
            'results' => 'GET /api/results/{attempt}',
            'solutions' => 'GET /api/results/{attempt}/solutions',
        ],
    ]);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:10,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    Route::middleware(AuthenticateApiToken::class)->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);

    Route::post('/attempts', [ExamAttemptController::class, 'start']);
    Route::get('/attempts/{attempt}', [ExamAttemptController::class, 'resume']);
    Route::get('/attempts/{attempt}/questions/{question}', [ExamAttemptController::class, 'question']);
    Route::put('/attempts/{attempt}/answers/{question}', [ExamAttemptController::class, 'saveAnswer']);
    Route::post('/attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])->middleware('throttle:10,1');
    Route::get('/attempts', [ResultController::class, 'history']);
    Route::get('/results/{attempt}/summary', [ResultController::class, 'summary']);
    Route::get('/results/{attempt}/solutions', [SolutionController::class, 'index']);
    Route::get('/results/{attempt}/solutions/{question}', [SolutionController::class, 'show']);
    Route::get('/results/{attempt}', [ResultController::class, 'show']);
});

Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/subjects/{subject}', [SubjectController::class, 'show']);

Route::get('/tests', [ModelTestController::class, 'index']);
Route::get('/tests/{test}', [ModelTestController::class, 'show']);

Route::middleware(AuthenticateApiToken::class)->prefix('admin')->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class);
    Route::post('/tests', [ModelTestController::class, 'store']);
    Route::patch('/tests/{test}', [ModelTestController::class, 'update']);
    Route::put('/tests/{test}/questions', [ModelTestController::class, 'replaceQuestions']);
    Route::post('/tests/{test}/publish', [ModelTestController::class, 'publish']);
});
