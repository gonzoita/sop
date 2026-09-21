<?php

namespace App\Http\Controllers;

use App\Models\ClientDocument;
use App\Models\SopRun;
use App\Services\Runs\RunDocumentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientDocumentController extends Controller
{
    /**
     * Get preview Markdown and title for publishing a deliverable.
     */
    public function preview(Request $request, SopRun $run, RunDocumentBuilder $builder): JsonResponse
    {
        $this->authorizeTeamManagement($request, $run);

        if (! $run->client_id) {
            abort(422, 'Esta ejecución no tiene un cliente asignado.');
        }

        $markdown = $builder->build($run);

        return response()->json([
            'title' => $run->title,
            'markdown' => $markdown,
        ]);
    }

    /**
     * Publish an immutable copy of the deliverable to the client portal.
     */
    public function store(Request $request, SopRun $run): JsonResponse|RedirectResponse
    {
        $this->authorizeTeamManagement($request, $run);

        if (! $run->client_id) {
            abort(422, 'Esta ejecución no tiene un cliente asignado.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'markdown' => ['required', 'string'],
        ], [
            'title.required' => 'El título del documento es obligatorio.',
            'markdown.required' => 'El contenido Markdown es obligatorio.',
        ]);

        $document = ClientDocument::create([
            'team_id' => $run->team_id,
            'client_id' => $run->client_id,
            'sop_run_id' => $run->id,
            'title' => $validated['title'],
            'markdown' => $validated['markdown'],
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        activity('client_documents')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties([
                'client_id' => $run->client_id,
                'sop_run_id' => $run->id,
                'title' => $document->title,
            ])
            ->log("Entregable '{$document->title}' publicado al portal del cliente");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Entregable publicado exitosamente al portal del cliente.',
                'document' => $document,
            ]);
        }

        return back()->with('banner', 'Entregable publicado exitosamente al portal del cliente.');
    }

    /**
     * Revoke a published document, hiding it from the client portal.
     */
    public function revoke(Request $request, ClientDocument $document): JsonResponse|RedirectResponse
    {
        if ($request->user()->hasRole('cliente')) {
            abort(403, 'Los clientes no tienen permiso para revocar documentos.');
        }

        if ((int) $request->user()->current_team_id !== (int) $document->team_id) {
            abort(404);
        }

        if (! ($request->user()->hasRole('admin') || $request->user()->hasRole('editor') || $request->user()->hasPermissionTo('manage-sops'))) {
            abort(403, 'Solo administradores y editores pueden revocar documentos.');
        }

        $document->update(['revoked_at' => now()]);

        activity('client_documents')
            ->performedOn($document)
            ->causedBy($request->user())
            ->log("Documento '{$document->title}' revocado del portal de cliente");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Documento revocado exitosamente del portal.',
                'document' => $document,
            ]);
        }

        return back()->with('banner', 'Documento revocado exitosamente del portal.');
    }

    /**
     * Ensure the user is a team admin or editor and belongs to the run's team.
     */
    protected function authorizeTeamManagement(Request $request, SopRun $run): void
    {
        $user = $request->user();

        if ($user->hasRole('cliente')) {
            abort(403, 'Los clientes no tienen autorización para publicar documentos.');
        }

        if ((int) $user->current_team_id !== (int) $run->team_id) {
            abort(404);
        }

        if (! ($user->hasRole('admin') || $user->hasRole('editor') || $user->hasPermissionTo('manage-sops'))) {
            abort(403, 'Solo administradores y editores pueden publicar documentos.');
        }
    }
}
