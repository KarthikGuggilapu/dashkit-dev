<?php

use Dashkit\Http\Controllers\DashboardController;
use Dashkit\Http\Controllers\DashkitAuthController;
use Dashkit\Http\Controllers\DashkitProfileController;
use Dashkit\Http\Controllers\DashkitSettingsController;
use Illuminate\Support\Facades\Route;

$auth = (array) config('dashkit.auth', []);
$authEnabled = (bool) ($auth['enabled'] ?? true);
$guard = (string) ($auth['guard'] ?? 'web');
$guestMiddleware = $authEnabled ? ['guest:'.$guard] : [];
$dashboardMiddleware = $authEnabled ? ['auth:'.$guard] : [];
$logoutMiddleware = $authEnabled ? ['auth:'.$guard] : [];
$prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

Route::middleware((array) config('dashkit.route_middleware', ['web']))
    ->group(function () use ($guestMiddleware, $dashboardMiddleware, $logoutMiddleware, $prefix): void {
        Route::middleware($guestMiddleware)->group(function (): void {
            Route::get('/login', [DashkitAuthController::class, 'showLogin'])->name('dashkit.login');
            Route::post('/login', [DashkitAuthController::class, 'login'])->name('dashkit.login.attempt');
            Route::get('/forgot-password', [DashkitAuthController::class, 'showForgotPassword'])->name('dashkit.password.request');
            Route::post('/forgot-password', [DashkitAuthController::class, 'sendPasswordResetLink'])->name('dashkit.password.email');
            Route::get('/reset-password/{token}', [DashkitAuthController::class, 'showResetPassword'])->name('dashkit.password.reset');
            Route::post('/reset-password', [DashkitAuthController::class, 'resetPassword'])->name('dashkit.password.update');
        });

        Route::post('/logout', [DashkitAuthController::class, 'logout'])
            ->middleware($logoutMiddleware)
            ->name('dashkit.logout');

        Route::middleware($dashboardMiddleware)->group(function () use ($prefix): void {
            Route::get('/', [DashboardController::class, 'index'])->name('dashkit.home');
            Route::get('/'.$prefix, static fn () => redirect()->route('dashkit.home'));
            Route::get('/'.$prefix.'/search', [DashboardController::class, 'search'])->name('dashkit.search');
            Route::get('/'.$prefix.'/profile', [DashkitProfileController::class, 'show'])->name('dashkit.page.profile');
            Route::get('/'.$prefix.'/settings', [DashkitSettingsController::class, 'show'])->name('dashkit.settings');
            Route::post('/'.$prefix.'/settings/general', [DashkitSettingsController::class, 'updateGeneral'])->name('dashkit.settings.general.update');
            Route::post('/'.$prefix.'/settings/mail', [DashkitSettingsController::class, 'updateMail'])->name('dashkit.settings.mail.save');
            Route::post('/'.$prefix.'/settings/topbar', [DashkitSettingsController::class, 'updateTopbar'])->name('dashkit.settings.topbar.update');
            Route::post('/'.$prefix.'/settings/sidebar/add', [DashkitSettingsController::class, 'addSidebarItem'])->name('dashkit.settings.sidebar.add');
            Route::post('/'.$prefix.'/settings/sidebar/remove', [DashkitSettingsController::class, 'removeSidebarItem'])->name('dashkit.settings.sidebar.remove');
            Route::post('/'.$prefix.'/profile', [DashkitProfileController::class, 'updateProfile'])->name('dashkit.profile.update');
            Route::post('/'.$prefix.'/profile/password', [DashkitProfileController::class, 'updatePassword'])->name('dashkit.profile.password.update');
            Route::post('/'.$prefix.'/profile/mail-settings', [DashkitProfileController::class, 'updateMailSettings'])->name('dashkit.settings.mail.update');

            Route::get('/'.$prefix.'/{page}', [DashboardController::class, 'page'])
                ->where('page', '[A-Za-z0-9\-_]+')
                ->name('dashkit.page');
        });
    });
