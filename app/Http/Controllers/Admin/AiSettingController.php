<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBudget;
use App\Models\AiCredential;
use App\Models\AiGeneration;
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

        $currentMonthCost = (float) AiGeneration::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'succeeded')
            ->sum('cost_usd');

        $totalGenerationsCount = AiGeneration::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalTokensThisMonth = (int) (AiGeneration::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'succeeded')
            ->selectRaw('SUM(input_tokens + output_tokens) as total_tokens')
            ->value('total_tokens') ?? 0);

        $recentGenerations = AiGeneration::with(['run.sop:id,title', 'step:id,title'])
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($gen) => [
                'id' => $gen->id,
                'model' => $gen->model,
                'provider' => $gen->provider,
                'status' => $gen->status,
                'cost_usd' => (float) $gen->cost_usd,
                'total_tokens' => (int) ($gen->input_tokens + $gen->output_tokens),
                'latency_ms' => $gen->latency_ms,
                'sop_title' => $gen->run?->sop?->title ?? 'SOP',
                'step_title' => $gen->step?->title ?? 'Paso',
                'created_at' => $gen->created_at?->format('d/m/Y H:i'),
                'error' => $gen->error,
            ]);

        return Inertia::render('Admin/Ai/Index', [
            'credentials' => $credentials,
            'budget' => [
                'id' => $budget->id,
                'monthly_limit_usd' => (float) $budget->monthly_limit_usd,
                'alert_at_percent' => (int) $budget->alert_at_percent,
                'current_month_cost_usd' => $currentMonthCost,
            ],
            'stats' => [
                'total_generations' => $totalGenerationsCount,
                'total_tokens' => $totalTokensThisMonth,
            ],
            'recent_generations' => $recentGenerations,
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
