<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBudget;
use App\Models\AiCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    /**
     * Authorize that only administrators can access AI settings.
     */
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403, 'Solo administradores pueden gestionar la configuración y presupuesto de IA.');
    }

    /**
     * Display the AI configuration and budget management page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $teamId = $request->user()->current_team_id;

        $credentials = AiCredential::with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn ($cred) => [
                'id' => $cred->id,
                'provider' => $cred->provider,
                'label' => $cred->label,
                'masked_api_key' => $cred->masked_api_key,
                'is_active' => (bool) $cred->is_active,
                'last_used_at' => $cred->last_used_at?->format('d/m/Y H:i'),
                'creator_name' => $cred->creator->name ?? 'Sistema',
                'created_at' => $cred->created_at?->format('d/m/Y H:i'),
            ]);

        $budget = AiBudget::firstOrCreate(
            ['team_id' => $teamId],
            ['monthly_limit_usd' => 50.00, 'alert_at_percent' => 80]
        );

        return Inertia::render('Admin/Ai/Index', [
            'credentials' => $credentials,
            'budget' => [
                'id' => $budget->id,
                'monthly_limit_usd' => (float) $budget->monthly_limit_usd,
                'alert_at_percent' => (int) $budget->alert_at_percent,
                'current_month_cost_usd' => 0.00, // Se conectará con ai_generations en pasos posteriores
            ],
        ]);
    }

    /**
     * Store a new AI API credential securely.
     */
    public function storeCredential(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:openrouter'],
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        AiCredential::create([
            'provider' => $validated['provider'],
            'label' => $validated['label'] ?? null,
            'api_key' => $validated['api_key'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('banner', 'Credencial de IA configurada con éxito.');
    }

    /**
     * Toggle the active state of an AI credential.
     */
    public function toggleCredential(Request $request, AiCredential $credential): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $credential->update([
            'is_active' => !$credential->is_active,
        ]);

        return back()->with('banner', 'Estado de la credencial actualizado.');
    }

    /**
     * Remove an AI credential.
     */
    public function destroyCredential(Request $request, AiCredential $credential): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $credential->delete();

        return back()->with('banner', 'Credencial de IA eliminada.');
    }

    /**
     * Update monthly budget and alert threshold.
     */
    public function updateBudget(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'monthly_limit_usd' => ['required', 'numeric', 'min:0', 'max:10000'],
            'alert_at_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $teamId = $request->user()->current_team_id;

        AiBudget::updateOrCreate(
            ['team_id' => $teamId],
            [
                'monthly_limit_usd' => $validated['monthly_limit_usd'],
                'alert_at_percent' => $validated['alert_at_percent'],
            ]
        );

        return back()->with('banner', 'Límites de presupuesto actualizados correctamente.');
    }
}
