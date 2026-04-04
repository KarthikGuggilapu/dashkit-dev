<?php

use Dashkit\Http\Controllers\DashkitSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashkit GUI Setup Wizard Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when a valid setup token file exists at
| storage/app/dashkit/setup-token.json.  They are automatically removed
| (via the token file deletion) after the installation completes.
|
*/

Route::middleware(['web'])
    ->prefix('dashkit-console')
    ->name('dashkit.setup.')
    ->group(function (): void {
        Route::get('/', [DashkitSetupController::class, 'index'])->name('index');
        Route::post('/test-connection', [DashkitSetupController::class, 'testConnection'])->name('test-connection');
        Route::post('/install', [DashkitSetupController::class, 'install'])->name('install');
    });
