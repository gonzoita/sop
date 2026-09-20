<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['throttle:password-reset'])->group(function () {
    Route::post('/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])
        ->middleware(['web', 'guest:'.config('fortify.guard')])
        ->name('password.email');

    Route::post('/reset-password', [\Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'store'])
        ->middleware(['web', 'guest:'.config('fortify.guard')])
        ->name('password.update');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::middleware(['admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])
            ->name('audit-logs.index');
    });
});
