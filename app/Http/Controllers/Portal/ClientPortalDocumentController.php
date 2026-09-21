<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientPortalDocumentController extends Controller
{
    /**
     * Display a listing of published deliverables for the authenticated client user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $user->clients()->pluck('clients.id')->toArray();

        $documents = ClientDocument::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->active()
            ->with(['sopRun.sop:id,title', 'client:id,name'])
            ->latest('published_at')
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'sop_title' => $doc->sopRun?->sop?->title ?? 'Procedimiento',
                    'client_name' => $doc->client?->name ?? '',
                    'published_at' => $doc->published_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Portal/Documents/Index', [
            'documents' => $documents,
            'clients' => $user->clients()->get(['clients.id', 'clients.name']),
        ]);
    }

    /**
     * Display the specified published document.
     * Returns 404 if revoked or belonging to another client/team to prevent ID enumeration.
     */
    public function show(Request $request, int $document): Response
    {
        $doc = $this->findAuthorizedActiveDocument($request, $document);

        $html = (string) Str::markdown($doc->markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return Inertia::render('Portal/Documents/Show', [
            'document' => [
                'id' => $doc->id,
                'title' => $doc->title,
                'markdown' => $doc->markdown,
                'html' => $html,
                'sop_title' => $doc->sopRun?->sop?->title ?? 'Procedimiento',
                'client_name' => $doc->client?->name ?? '',
                'published_at' => $doc->published_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Download the document as a Markdown (.md) file.
     */
    public function downloadMarkdown(Request $request, int $document): HttpResponse
    {
        $doc = $this->findAuthorizedActiveDocument($request, $document);

        $slug = Str::slug($doc->title) ?: 'entregable';
        $filename = "{$slug}.md";

        return response($doc->markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download the document as a clean PDF file.
     */
    public function downloadPdf(Request $request, int $document): HttpResponse
    {
        $doc = $this->findAuthorizedActiveDocument($request, $document);

        $htmlContent = (string) Str::markdown($doc->markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $slug = Str::slug($doc->title) ?: 'entregable';
        $filename = "{$slug}.pdf";

        $pdf = Pdf::loadView('portal.document-pdf', [
            'document' => $doc,
            'htmlContent' => $htmlContent,
        ]);

        return $pdf->download($filename);
    }

    /**
     * Find active document belonging to the user's clients or abort with 404.
     */
    protected function findAuthorizedActiveDocument(Request $request, int $document): ClientDocument
    {
        $user = $request->user();
        $clientIds = $user->clients()->pluck('clients.id')->toArray();

        $doc = ClientDocument::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->active()
            ->with(['sopRun.sop:id,title', 'client:id,name'])
            ->find($document);

        if (! $doc) {
            abort(404, 'Documento no encontrado o no disponible.');
        }

        return $doc;
    }
}
