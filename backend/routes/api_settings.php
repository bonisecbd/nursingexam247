<?php

use App\Http\Controllers\Api\SettingController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Settings Module
|--------------------------------------------------------------------------
| Wave 2 module routes. Register this module's endpoints in this file only.
| Do not edit routes/api.php, bootstrap/app.php, or other modules' files.
|
| Docs: docs/MODULES.md section 20 (Settings).
*/

// Public allowlist: branding, support contact, feature flags, and public
// exam rules only. Never secrets or private operational settings.
Route::get('/settings/public', [SettingController::class, 'publicSettings']);

// Admin read/update of operational settings; the `admin` role is enforced
// inside the controller (matching the other admin route modules).
Route::middleware(AuthenticateApiToken::class)->prefix('admin')->group(function (): void {
    Route::get('/settings', [SettingController::class, 'adminIndex']);
    Route::patch('/settings', [SettingController::class, 'adminUpdate']);
});
