<?php

namespace App\Http\Controllers;

use App\Actions\Sop\AdvanceRun;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Services\Runs\RunDocumentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $publishedDocuments = \App\Models\ClientDocument::where('sop_run_id', $run->id)
            ->with('publisher:id,name')
            ->latest('published_at')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'published_at' => $d->published_at?->format('d/m/Y H:i'),
                'revoked_at' => $d->revoked_at?->format('d/m/Y H:i'),
                'is_revoked' => $d->isRevoked(),
                'publisher_name' => $d->publisher?->name ?? 'Equipo',
            ]);

        return Inertia::render('Runs/Show', [
            'run' => $run,
            'teamMembers' => $teamMembers,
            'publishedDocuments' => $publishedDocuments,
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
     * Approve an AI generated output, making it available to downstream steps.
     */
    public function approveAiStep(Request $request, SopRun $run, SopRunStep $step): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $run);

        if ($step->sop_run_id !== $run->id || $step->status !== 'awaiting_approval') {
            abort(422, 'Este paso no está pendiente de aprobación.');
        }

        $validated = $request->validate([
            'edited_content' => ['nullable', 'string'],
        ]);

        $currentOutput = $step->output ?? [];
        $finalContent = $validated['edited_content'] ?? ($currentOutput['content'] ?? '');
        $outputKey = $currentOutput['output_key'] ?? null;

        $step->status = 'approved';
        $step->completed_at = now();
        $step->output = array_merge($currentOutput, [
            'content' => $finalContent,
            'approved_by' => $request->user()->id,
            'approved_at' => now()->toIso8601String(),
        ]);
        $step->save();

        if ($outputKey) {
            $outputs = $run->outputs ?? [];
            $outputs[$outputKey] = $finalContent;
            $run->outputs = $outputs;
        }

        // Recalcular estado de la corrida
        $hasAwaiting = $run->steps()->where('status', 'awaiting_approval')->exists();
        if (! $hasAwaiting) {
            $allDone = $run->steps()->get()->every(fn ($s) => in_array($s->status, ['completed', 'approved', 'skipped']));
            $run->status = $allDone ? 'completed' : 'in_progress';
            if ($allDone && ! $run->completed_at) {
                $run->completed_at = now();
            }
        }
        $run->save();

        \App\Events\RunStepApproved::dispatch($run, $step);
        \App\Jobs\RunAiTask::dispatchNextEligibleAiTasks($run);

        activity('ai_approval')
            ->performedOn($step)
            ->causedBy($request->user())
            ->log("Paso de IA {$step->block_id} aprobado para variable '{$outputKey}'");

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'step' => $step, 'run' => $run]);
        }

        return back()->with('banner', 'Salida de IA aprobada e inyectada exitosamente.');
    }

    /**
     * Reject an AI generated output with a reason.
     */
    public function rejectAiStep(Request $request, SopRun $run, SopRunStep $step): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $run);

        if ($step->sop_run_id !== $run->id) {
            abort(422, 'Paso no válido para esta corrida.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $step->status = 'rejected';
        $step->notes = $validated['reason'];
        $step->save();

        activity('ai_approval')
            ->performedOn($step)
            ->causedBy($request->user())
            ->log("Paso de IA {$step->block_id} rechazado: {$validated['reason']}");

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'step' => $step]);
        }

        return back()->with('banner', 'Salida de IA rechazada.');
    }

    /**
     * Re-queue an AI generation task.
     */
    public function retryAiStep(Request $request, SopRun $run, SopRunStep $step): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $run);

        if ($step->sop_run_id !== $run->id || $step->block_type !== 'ai_task') {
            abort(422, 'Solo se pueden reintentar pasos de IA.');
        }

        $step->status = 'pending';
        $step->notes = null;
        $step->save();

        \App\Jobs\RunAiTask::dispatch($step->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'step' => $step]);
        }

        return back()->with('banner', 'Generación de IA encolada para ejecución en segundo plano.');
    }

    /**
     * Export the finalized deliverable Markdown for a run.
     */
    public function exportDeliverable(Request $request, SopRun $run, RunDocumentBuilder $builder): StreamedResponse
    {
        Gate::authorize('view', $run);

        $markdown = $builder->build($run);

        activity('sop_runs')
            ->performedOn($run)
            ->causedBy($request->user())
            ->log('Entregable Markdown exportado');

        $sopSlug = $run->sop?->slug ?: Str::slug($run->sop?->title ?: 'sop');
        $clientSlug = $run->client ? Str::slug($run->client->name) : 'general';
        $date = now()->format('Y-m-d');
        $filename = "{$sopSlug}-{$clientSlug}-{$date}.md";

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $filename, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
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
