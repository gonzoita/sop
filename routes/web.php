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

    // SOPs (Constructor y Gestión)
    Route::resource('sops', \App\Http\Controllers\SopController::class);
    Route::put('/sops/{sop}/draft', [\App\Http\Controllers\SopController::class, 'saveDraft'])->name('sops.draft.save');
    Route::post('/sops/{sop}/publish', [\App\Http\Controllers\SopController::class, 'publish'])->name('sops.publish');
    Route::post('/sops/{sop}/duplicate', [\App\Http\Controllers\SopController::class, 'duplicate'])->name('sops.duplicate');
    Route::get('/sops/{sop}/export/markdown', [\App\Http\Controllers\SopController::class, 'exportMarkdown'])->name('sops.export.markdown');
    Route::get('/sops/{sop}/export/pdf', [\App\Http\Controllers\SopController::class, 'exportPdf'])->name('sops.export.pdf');

    Route::middleware(['admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])
            ->name('audit-logs.index');
    });
});
