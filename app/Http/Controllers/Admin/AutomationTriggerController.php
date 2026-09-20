<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationTrigger;
use App\Models\Sop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationTriggerController extends Controller
{
    /**
     * Display a listing of automation triggers.
     */
    public function index(Request $request): Response
    {
        $triggers = AutomationTrigger::with(['sop:id,title,status', 'creator:id,name'])
            ->latest()
            ->get()
            ->map(fn ($trigger) => [
                'id' => $trigger->id,
                'event' => $trigger->event,
                'sop_id' => $trigger->sop_id,
                'sop_title' => $trigger->sop->title ?? 'SOP Eliminado',
                'conditions' => $trigger->conditions,
                'is_active' => (bool) $trigger->is_active,
                'creator_name' => $trigger->creator->name ?? 'Sistema',
                'created_at' => $trigger->created_at?->format('d/m/Y H:i'),
            ]);

        $availableSops = Sop::where('status', 'published')
            ->whereNotNull('current_version_id')
            ->select(['id', 'title'])
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/AutomationTriggers/Index', [
            'triggers' => $triggers,
            'sops' => $availableSops,
        ]);
    }

    /**
     * Store a newly created automation trigger.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'in:client.created,run.completed,run.step.approved'],
            'sop_id' => ['required', 'exists:sops,id'],
            'is_active' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
        ]);

        AutomationTrigger::create([
            'event' => $validated['event'],
            'sop_id' => $validated['sop_id'],
            'conditions' => $validated['conditions'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('banner', 'Disparador de automatización creado con éxito.');
    }

    /**
     * Update the specified automation trigger.
     */
    public function update(Request $request, AutomationTrigger $automation_trigger): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
        ]);

        $automation_trigger->update($validated);

        return back()->with('banner', 'Disparador actualizado.');
    }

    /**
     * Remove the specified automation trigger.
     */
    public function destroy(AutomationTrigger $automation_trigger): RedirectResponse
    {
        $automation_trigger->delete();

        return back()->with('banner', 'Disparador de automatización eliminado.');
    }
}
