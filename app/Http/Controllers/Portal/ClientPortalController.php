<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Sop\AdvanceRun;
use App\Http\Controllers\Controller;
use App\Models\ClientFile;
use App\Models\SopRun;
use App\Models\SopRunStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPortalController extends Controller
{
    /**
     * Display a listing of runs assigned to the authenticated client user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $clientIds = $user->clients()->pluck('clients.id')->toArray();

        $runs = SopRun::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->with(['sop:id,title,description', 'client:id,name'])
            ->latest()
            ->get()
            ->map(function ($run) {
                return [
                    'id' => $run->id,
                    'title' => $run->title,
                    'status' => $run->status,
                    'sop_title' => $run->sop->title ?? 'SOP',
                    'client_name' => $run->client->name ?? '',
                    'created_at' => $run->created_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Portal/Runs/Index', [
            'runs' => $runs,
            'clients' => $user->clients()->get(['clients.id', 'clients.name']),
        ]);
    }

    /**
     * Display the specified run for the client user, strictly filtering to client blocks.
     */
    public function show(Request $request, SopRun $run): Response
    {
        $user = $request->user();
        $this->authorizeClientAccess($user, $run);

        $version = $run->sopVersion;
        $allBlocks = $version->blocks['blocks'] ?? [];

        // AISLAMIENTO ESTRICTO: El cliente ve ÚNICAMENTE los bloques marcados con filled_by = "client"
        $clientBlocks = array_values(array_filter($allBlocks, function ($block) {
            return ($block['props']['filled_by'] ?? null) === 'client';
        }));

        $clientBlockIds = array_column($clientBlocks, 'id');

        // Filtrar pasos de ejecución correspondientes solo a estos bloques
        $steps = $run->steps()
            ->whereIn('block_id', $clientBlockIds)
            ->get()
            ->keyBy('block_id');

        // Valores ya capturados para los inputs del cliente
        $clientInputs = [];
        foreach ($clientBlocks as $block) {
            $key = $block['props']['key'] ?? null;
            if ($key && isset($run->inputs[$key])) {
                $clientInputs[$key] = $run->inputs[$key];
            }
        }

        return Inertia::render('Portal/Runs/Show', [
            'run' => [
                'id' => $run->id,
                'title' => $run->title,
                'status' => $run->status,
                'client_name' => $run->client->name ?? '',
                'created_at' => $run->created_at?->format('d/m/Y H:i'),
            ],
            'blocks' => $clientBlocks,
            'steps' => $steps,
            'inputs' => $clientInputs,
        ]);
    }

    /**
     * Advance a client step with input data.
     */
    public function advanceStep(Request $request, SopRun $run, SopRunStep $step, AdvanceRun $advanceAction): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClientAccess($user, $run);

        if ($step->sop_run_id !== $run->id) {
            abort(404);
        }

        // Validar que el bloque del paso pertenezca efectivamente al cliente
        $version = $run->sopVersion;
        $allBlocks = $version->blocks['blocks'] ?? [];
        $block = collect($allBlocks)->firstWhere('id', $step->block_id);

        if (! $block || ($block['props']['filled_by'] ?? null) !== 'client') {
            abort(403, 'No tienes permiso para modificar este paso.');
        }

        $request->validate([
            'value' => ['nullable'],
        ]);

        $advanceAction->execute($run, $step, $request->all(), $user);

        return back()->with('banner', 'Información guardada correctamente.');
    }

    /**
     * Secure file upload for client inputs.
     */
    public function uploadFile(Request $request, SopRun $run): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->authorizeClientAccess($user, $run);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB máximo
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,txt,csv,zip',
            ],
            'block_id' => ['required', 'string'],
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $systemFilename = Str::uuid()->toString() . '.' . $extension;

        // Guardar fuera de public_html (en private/client_files)
        $path = $file->storeAs("private/client_files/{$run->team_id}/{$run->id}", $systemFilename);

        $clientFile = ClientFile::create([
            'team_id' => $run->team_id,
            'client_id' => $run->client_id,
            'sop_run_id' => $run->id,
            'uploaded_by' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'id' => $clientFile->id,
            'original_name' => $clientFile->original_name,
            'file_path' => $clientFile->file_path,
            'download_url' => route('portal.runs.files.download', ['run' => $run->id, 'file' => $clientFile->id]),
        ]);
    }

    /**
     * Secure file download verifying permissions.
     */
    public function downloadFile(Request $request, SopRun $run, ClientFile $file): StreamedResponse
    {
        $user = $request->user();

        // Verificar que el usuario tenga acceso: o es del cliente asignado, o es del equipo dueño
        $isClientUser = $run->client_id && $user->clients()->where('clients.id', $run->client_id)->exists();
        $isTeamMember = (int) $user->current_team_id === (int) $run->team_id;

        if (! $isClientUser && ! $isTeamMember) {
            abort(403, 'No tienes autorización para acceder a este archivo.');
        }

        if ($file->sop_run_id !== $run->id) {
            abort(404, 'Archivo no asociado a esta ejecución.');
        }

        if (! Storage::exists($file->file_path)) {
            abort(404, 'El archivo no existe en el almacenamiento.');
        }

        return Storage::download($file->file_path, $file->original_name);
    }

    /**
     * Authorize that the user belongs to the client assigned to the run.
     */
    protected function authorizeClientAccess($user, SopRun $run): void
    {
        if (! $run->client_id || ! $user->clients()->where('clients.id', $run->client_id)->exists()) {
            abort(403, 'No tienes autorización para acceder a este procedimiento.');
        }
    }
}
