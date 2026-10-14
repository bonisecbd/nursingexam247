<?php

use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | API Routes — Notification Module (docs/MODULES.md section 17)
 |--------------------------------------------------------------------------
 | Wave 2 module routes. All endpoints are bearer-protected; authorization
 | (caller scoping / admin role) is enforced inside the services and
 | controllers. Registered with the standard `api` middleware group and the
 | /api prefix from bootstrap/app.php.
 */

Route::middleware(AuthenticateApiToken::class)->group(function (): void {
    // Student-facing: own notifications, read tracking, preferences.
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
    Route::patch('/notification-preferences', [NotificationController::class, 'updatePreferences']);

    // Admin: announcement management. The controller requires the `admin`
    // role, matching the other admin route groups.
    Route::prefix('admin')->group(function (): void {
        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
    });
});
