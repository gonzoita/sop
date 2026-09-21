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

    // Ejecuciones de SOPs (Equipo)
    Route::resource('runs', \App\Http\Controllers\SopRunController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/runs/{run}/steps/{step}/advance', [\App\Http\Controllers\SopRunController::class, 'advanceStep'])->name('runs.steps.advance');
    Route::put('/runs/{run}/steps/{step}/assignment', [\App\Http\Controllers\SopRunController::class, 'updateStepAssignment'])->name('runs.steps.assignment');
    Route::post('/runs/{run}/steps/{step}/approve-ai', [\App\Http\Controllers\SopRunController::class, 'approveAiStep'])->name('runs.steps.approve-ai');
    Route::post('/runs/{run}/steps/{step}/reject-ai', [\App\Http\Controllers\SopRunController::class, 'rejectAiStep'])->name('runs.steps.reject-ai');
    Route::post('/runs/{run}/steps/{step}/retry-ai', [\App\Http\Controllers\SopRunController::class, 'retryAiStep'])->name('runs.steps.retry-ai');
    Route::get('/runs/{run}/export/deliverable', [\App\Http\Controllers\SopRunController::class, 'exportDeliverable'])->name('runs.export.deliverable');

    // Gestión de Clientes (Agencia)
    Route::resource('clients', \App\Http\Controllers\ClientController::class)->only(['index', 'store', 'destroy']);

    // Librería de Skills de IA
    Route::resource('skills', \App\Http\Controllers\SkillController::class);
    Route::post('/skills/{skill}/publish-version', [\App\Http\Controllers\SkillController::class, 'publishVersion'])->name('skills.publish-version');
    Route::post('/skills/{skill}/versions/{version}/set-current', [\App\Http\Controllers\SkillController::class, 'setCurrentVersion'])->name('skills.set-current');
    Route::get('/skills/{skill}/export/markdown', [\App\Http\Controllers\SkillController::class, 'exportMarkdown'])->name('skills.export.markdown');
    Route::post('/skills/{skill}/import/preview', [\App\Http\Controllers\SkillController::class, 'previewImport'])->name('skills.import.preview');
    Route::post('/skills/{skill}/import/confirm', [\App\Http\Controllers\SkillController::class, 'confirmImport'])->name('skills.import.confirm');
    Route::post('/skills/{skill}/import/markdown', [\App\Http\Controllers\SkillController::class, 'importMarkdown'])->name('skills.import.markdown');

    Route::middleware(['admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])
            ->name('audit-logs.index');
        Route::resource('automation-triggers', \App\Http\Controllers\Admin\AutomationTriggerController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Configuración y presupuesto de IA
        Route::get('/ai-settings', [\App\Http\Controllers\Admin\AiSettingController::class, 'index'])
            ->name('ai-settings.index');
        Route::post('/ai-settings/credentials', [\App\Http\Controllers\Admin\AiSettingController::class, 'storeCredential'])
            ->name('ai-settings.credentials.store');
        Route::patch('/ai-settings/credentials/{credential}/toggle', [\App\Http\Controllers\Admin\AiSettingController::class, 'toggleCredential'])
            ->name('ai-settings.credentials.toggle');
        Route::delete('/ai-settings/credentials/{credential}', [\App\Http\Controllers\Admin\AiSettingController::class, 'destroyCredential'])
            ->name('ai-settings.credentials.destroy');
        Route::put('/ai-settings/budget', [\App\Http\Controllers\Admin\AiSettingController::class, 'updateBudget'])
            ->name('ai-settings.budget.update');
    });

    // Invitación de clientes desde panel de agencia
    Route::post('/clients/{client}/invitations', [\App\Http\Controllers\Portal\ClientInvitationController::class, 'store'])
        ->name('clients.invitations.store');
});

// Rutas públicas de invitación a clientes
Route::get('/portal/invitation/{token}', [\App\Http\Controllers\Portal\ClientInvitationController::class, 'show'])
    ->name('portal.invitations.accept');
Route::post('/portal/invitation/{token}', [\App\Http\Controllers\Portal\ClientInvitationController::class, 'accept'])
    ->name('portal.invitations.process');

// Portal exclusivo para clientes (Aislamiento total)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'portal.client',
])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Portal\ClientPortalController::class, 'index'])->name('runs.index');
    Route::get('/runs/{run}', [\App\Http\Controllers\Portal\ClientPortalController::class, 'show'])->name('runs.show');
    Route::post('/runs/{run}/steps/{step}/advance', [\App\Http\Controllers\Portal\ClientPortalController::class, 'advanceStep'])->name('runs.steps.advance');
    Route::post('/runs/{run}/upload', [\App\Http\Controllers\Portal\ClientPortalController::class, 'uploadFile'])->name('runs.upload');
    Route::get('/runs/{run}/files/{file}', [\App\Http\Controllers\Portal\ClientPortalController::class, 'downloadFile'])->name('runs.files.download');
});

