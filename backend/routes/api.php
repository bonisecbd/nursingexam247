<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminExamController;
use App\Http\Controllers\Api\AdminQuestionController;
use App\Http\Controllers\Api\AdminResultController;
use App\Http\Controllers\Api\AdminSubjectController;
use App\Http\Controllers\Api\AdminUserController;
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
            'admin_dashboard' => 'GET /api/admin/dashboard',
            'admin_users' => 'GET /api/admin/users',
            'admin_user_details' => 'GET /api/admin/users/{user}',
            'admin_user_status' => 'PATCH /api/admin/users/{user}/status',
            'admin_subjects' => 'GET|POST /api/admin/subjects',
            'admin_subject_details' => 'PATCH /api/admin/subjects/{subject}',
            'admin_questions' => 'GET|POST /api/admin/questions',
            'admin_question_details' => 'PATCH /api/admin/questions/{question}',
            'admin_tests' => 'GET|POST /api/admin/tests',
            'admin_test_details' => 'GET|PATCH /api/admin/tests/{test}',
            'admin_test_questions' => 'PUT /api/admin/tests/{test}/questions',
            'admin_test_publish' => 'POST /api/admin/tests/{test}/publish',
            'admin_exams' => 'GET /api/admin/exams',
            'admin_exam_details' => 'GET /api/admin/exams/{attempt}',
            'admin_results' => 'GET /api/admin/results',
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
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus']);
    Route::get('/subjects', [AdminSubjectController::class, 'index']);
    Route::post('/subjects', [AdminSubjectController::class, 'store']);
    Route::patch('/subjects/{subject}', [AdminSubjectController::class, 'update']);
    Route::get('/questions', [AdminQuestionController::class, 'index']);
    Route::post('/questions', [AdminQuestionController::class, 'store']);
    Route::patch('/questions/{question}', [AdminQuestionController::class, 'update']);
    Route::get('/tests', [ModelTestController::class, 'adminIndex']);
    Route::post('/tests', [ModelTestController::class, 'store']);
    Route::get('/tests/{test}', [ModelTestController::class, 'adminShow']);
    Route::patch('/tests/{test}', [ModelTestController::class, 'update']);
    Route::put('/tests/{test}/questions', [ModelTestController::class, 'replaceQuestions']);
    Route::post('/tests/{test}/publish', [ModelTestController::class, 'publish']);
    Route::get('/exams', [AdminExamController::class, 'index']);
    Route::get('/exams/{attempt}', [AdminExamController::class, 'show']);
    Route::get('/results', [AdminResultController::class, 'index']);
});
