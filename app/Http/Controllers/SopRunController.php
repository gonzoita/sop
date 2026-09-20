<?php

namespace App\Http\Controllers;

use App\Actions\Sop\AdvanceRun;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SopRunController extends Controller
{
    /**
     * Display a listing of the SOP runs.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SopRun::class);

        $query = SopRun::query()
            ->with(['sop', 'sopVersion', 'client', 'assignee', 'starter'])
            ->withCount([
                'steps',
                'steps as completed_steps_count' => fn ($q) => $q->whereIn('status', ['completed', 'approved', 'skipped']),
            ])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('sop', fn ($sq) => $sq->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $runs = $query->paginate(12)->withQueryString();

        $team = $request->user()->currentTeam;
        $teamMembers = $team
            ? $team->allUsers()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            : [];

        $clients = Client::orderBy('name')->get(['id', 'name']);

        $availableSops = Sop::query()
            ->whereNotNull('current_version_id')
            ->where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title', 'current_version_id']);

        return Inertia::render('Runs/Index', [
            'runs' => $runs,
            'clients' => $clients,
            'teamMembers' => $teamMembers,
            'availableSops' => $availableSops,
            'filters' => $request->only(['search', 'status', 'client_id', 'assigned_to']),
        ]);
    }

    /**
     * Store a newly created SOP run in storage.
     */
    public function store(Request $request, StartSopRun $startSopRun): RedirectResponse
    {
        Gate::authorize('create', SopRun::class);

        $validated = $request->validate([
            'sop_id' => ['required', 'exists:sops,id'],
            'sop_version_id' => ['nullable', 'exists:sop_versions,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $sop = Sop::findOrFail($validated['sop_id']);

        $run = $startSopRun->execute($request->user(), $sop, $validated);

        return redirect()->route('runs.show', $run->id)
            ->with('success', 'Ejecución iniciada exitosamente.');
    }

    /**
     * Display the specified SOP run.
     */
    public function show(SopRun $run): Response
    {
        Gate::authorize('view', $run);

        $run->load([
            'sop',
            'sopVersion.creator',
            'client',
            'starter',
            'assignee',
            'steps.assignee',
        ]);

        $team = $run->team;
        $teamMembers = $team
            ? $team->allUsers()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            : [];

        return Inertia::render('Runs/Show', [
            'run' => $run,
            'teamMembers' => $teamMembers,
        ]);
    }

    /**
     * Advance a specific step in the SOP run.
     */
    public function advanceStep(Request $request, SopRun $run, SopRunStep $step, AdvanceRun $advanceRun): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $run);

        $updatedRun = $advanceRun->execute($run, $step, $request->all(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'run' => $updatedRun,
            ]);
        }

        return redirect()->back()->with('success', 'Paso completado correctamente.');
    }

    /**
     * Update assignment, due date or notes for a step.
     */
    public function updateStepAssignment(Request $request, SopRun $run, SopRunStep $step): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $run);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $originalAssignee = $step->assigned_to;
        $step->update($validated);

        if (! empty($validated['assigned_to']) && (int) $validated['assigned_to'] !== (int) $originalAssignee) {
            $assignee = \App\Models\User::find($validated['assigned_to']);
            if ($assignee) {
                $assignee->notify(new \App\Notifications\StepAssignedNotification($run, $step));
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'step' => $step->fresh('assignee'),
            ]);
        }

        return redirect()->back()->with('success', 'Asignación de paso actualizada.');
    }

    /**
     * Remove the specified SOP run from storage.
     */
    public function destroy(SopRun $run): RedirectResponse
    {
        Gate::authorize('delete', $run);

        $run->delete();

        return redirect()->route('runs.index')
            ->with('success', 'Ejecución eliminada.');
    }
}
