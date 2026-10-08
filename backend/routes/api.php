<?php

use App\Http\Controllers\Api\AdminCouponController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminExamController;
use App\Http\Controllers\Api\AdminQuestionController;
use App\Http\Controllers\Api\AdminResultController;
use App\Http\Controllers\Api\AdminSubjectController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\ModelTestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\SolutionController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WalletController;
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
            'leaderboards' => 'GET /api/leaderboards?period=daily|weekly|monthly|overall',
            'my_leaderboard_position' => 'GET /api/leaderboards/{period}/me',
            'gamification' => 'GET /api/gamification/me',
            'badges' => 'GET /api/gamification/badges',
            'achievements' => 'GET /api/gamification/achievements',
            'admin_gamification_rules' => 'GET|PATCH /api/admin/gamification/rules',
            'referrals' => 'GET /api/referrals/me',
            'my_referral_invites' => 'GET /api/referrals/me/invites',
            'redeem_referral' => 'POST /api/referrals/redeem',
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
            'validate_coupon' => 'POST /api/coupons/validate',
            'wallet' => 'GET /api/wallet',
            'wallet_transactions' => 'GET /api/wallet/transactions',
            'wallet_topup' => 'POST /api/wallet/topup',
            'wallet_spend' => 'POST /api/wallet/spend',
            'subscription_packages' => 'GET /api/subscription-packages',
            'my_subscriptions' => 'GET /api/subscriptions/me',
            'subscription_checkout' => 'POST /api/subscriptions/checkout',
            'test_access' => 'GET /api/tests/{test}/access',
            'payment_checkout' => 'POST /api/payments/checkout',
            'payment' => 'GET /api/payments/{payment}',
            'payment_verify' => 'POST /api/payments/{payment}/verify',
            'admin_coupons' => 'GET|POST /api/admin/coupons',
            'admin_coupon_details' => 'PATCH /api/admin/coupons/{coupon}',
            'admin_coupon_disable' => 'POST /api/admin/coupons/{coupon}/disable',
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
    Route::get('/leaderboards/{period}/me', [LeaderboardController::class, 'me']);
    Route::get('/gamification/me', [GamificationController::class, 'me']);
    Route::get('/gamification/achievements', [GamificationController::class, 'achievements']);
    Route::get('/referrals/me', [ReferralController::class, 'me']);
    Route::get('/referrals/me/invites', [ReferralController::class, 'invites']);
    Route::post('/referrals/redeem', [ReferralController::class, 'redeem']);

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
Route::get('/leaderboards', [LeaderboardController::class, 'index']);
Route::get('/gamification/badges', [GamificationController::class, 'badges']);

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
    Route::get('/gamification/rules', [GamificationController::class, 'adminRules']);
    Route::patch('/gamification/rules', [GamificationController::class, 'updateAdminRules']);
});

// ---------------------------------------------------------------------------
// Coupon Module (docs/MODULES.md section 12)
// Student-facing validation/quote. Management lives under /api/admin/coupons
// below; authorization for admin operations is enforced inside the controller.
// ---------------------------------------------------------------------------
Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::post('/coupons/validate', [CouponController::class, 'validate']);
});

// ---------------------------------------------------------------------------
// Wallet Module (docs/MODULES.md section 13)
// Balances and ledger entries are server-derived; clients never send amounts.
// ---------------------------------------------------------------------------
Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('/wallet/transactions/{transaction}', [WalletController::class, 'transaction']);
    Route::post('/wallet/topup', [WalletController::class, 'topup']);
    Route::post('/wallet/spend', [WalletController::class, 'spend']);
});

// ---------------------------------------------------------------------------
// Subscription/Premium Module (docs/MODULES.md section 14)
// ---------------------------------------------------------------------------
Route::get('/subscription-packages', [SubscriptionController::class, 'packages']);

Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::get('/subscriptions/me', [SubscriptionController::class, 'me']);
    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::get('/tests/{test}/access', [SubscriptionController::class, 'testAccess']);
});

// ---------------------------------------------------------------------------
// Payment Module (docs/MODULES.md section 15)
// The callback route is intentionally public: it authenticates with an HMAC
// signature, not a student bearer token. External gateway integration is NOT
// verified end to end in this environment.
// ---------------------------------------------------------------------------
Route::post('/payments/callback/{provider}', [PaymentController::class, 'callback']);

Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    Route::post('/payments/checkout', [PaymentController::class, 'checkout']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify']);
});

// ---------------------------------------------------------------------------
// Admin: Coupon Module management (docs/MODULES.md section 12).
// The controller requires the `admin` role, matching the other admin routes.
// ---------------------------------------------------------------------------
Route::middleware(AuthenticateApiToken::class)->prefix('admin')->group(function (): void {
    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::post('/coupons', [AdminCouponController::class, 'store']);
    Route::patch('/coupons/{coupon}', [AdminCouponController::class, 'update']);
    Route::post('/coupons/{coupon}/disable', [AdminCouponController::class, 'disable']);
});
