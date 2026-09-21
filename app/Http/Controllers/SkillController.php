<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\Sop;
use App\Support\Diff\LineDiffer;
use App\Support\Skills\SkillMarkdownParser;
use App\Support\Sop\VariableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkillController extends Controller
{
    /**
     * Display a listing of skills for the active team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manage-sops');

        $skills = Skill::with(['currentVersion', 'creator:id,name'])
            ->withCount('versions')
            ->latest()
            ->get()
            ->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'description' => $skill->description,
                'tags' => $skill->tags ?? [],
                'current_version_id' => $skill->current_version_id,
                'current_version_number' => $skill->currentVersion?->version_number ?? 1,
                'current_variables' => $skill->currentVersion?->variables ?? [],
                'versions_count' => $skill->versions_count,
                'creator_name' => $skill->creator->name ?? 'Sistema',
                'created_at' => $skill->created_at?->format('d/m/Y'),
                'updated_at' => $skill->updated_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Skills/Index', [
            'skills' => $skills,
        ]);
    }

    /**
     * Store a newly created skill with its initial version.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-sops');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'instructions' => ['required', 'string'],
        ]);

        $teamId = $request->user()->current_team_id;
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        // Asegurar unicidad de slug por equipo
        $originalSlug = $slug;
        $counter = 1;
        while (Skill::withoutGlobalScopes()->where('team_id', $teamId)->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $variables = VariableResolver::extractVariables($validated['instructions']);

        $skill = DB::transaction(function () use ($validated, $slug, $variables, $request) {
            $newSkill = Skill::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'created_by' => $request->user()->id,
            ]);

            $version = SkillVersion::create([
                'skill_id' => $newSkill->id,
                'version_number' => 1,
                'instructions' => $validated['instructions'],
                'variables' => $variables,
                'source' => 'manual',
                'changelog' => 'Versión inicial',
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            $newSkill->update(['current_version_id' => $version->id]);

            return $newSkill;
        });

        return redirect()->route('skills.show', $skill->id)
            ->with('banner', "Skill '{$skill->name}' creada con éxito.");
    }

    /**
     * Display the specified skill with full version history.
     */
    public function show(Skill $skill): Response
    {
        Gate::authorize('manage-sops');

        $skill->load(['currentVersion', 'creator:id,name']);

        $versions = $skill->versions()
            ->with('creator:id,name')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'instructions' => $v->instructions,
                'variables' => $v->variables ?? [],
                'source' => $v->source,
                'changelog' => $v->changelog,
                'creator_name' => $v->creator->name ?? 'Sistema',
                'created_at' => $v->created_at?->format('d/m/Y H:i'),
                'is_current' => $v->id === $skill->current_version_id,
            ]);

        return Inertia::render('Skills/Show', [
            'skill' => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'description' => $skill->description,
                'tags' => $skill->tags ?? [],
                'current_version_id' => $skill->current_version_id,
                'current_version' => $skill->currentVersion,
                'creator_name' => $skill->creator->name ?? 'Sistema',
                'created_at' => $skill->created_at?->format('d/m/Y'),
            ],
            'versions' => $versions,
        ]);
    }

    /**
     * Update skill metadata (name, description, tags).
     */
    public function update(Request $request, Skill $skill): RedirectResponse
    {
        Gate::authorize('manage-sops');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        $skill->update($validated);

        return back()->with('banner', 'Datos del skill actualizados.');
    }

    /**
     * Publish a new version of the skill instructions.
     */
    public function publishVersion(Request $request, Skill $skill): RedirectResponse
    {
        Gate::authorize('manage-sops');

        $validated = $request->validate([
            'instructions' => ['required', 'string'],
            'changelog' => ['required', 'string', 'max:500'],
        ]);

        $variables = VariableResolver::extractVariables($validated['instructions']);
        $nextVersion = ((int) $skill->versions()->max('version_number')) + 1;

        DB::transaction(function () use ($skill, $nextVersion, $validated, $variables, $request) {
            $version = SkillVersion::create([
                'skill_id' => $skill->id,
                'version_number' => $nextVersion,
                'instructions' => $validated['instructions'],
                'variables' => $variables,
                'source' => 'manual',
                'changelog' => $validated['changelog'],
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            $skill->update(['current_version_id' => $version->id]);
        });

        return back()->with('banner', "Nueva versión v{$nextVersion} publicada exitosamente.");
    }

    /**
     * Set an existing version as the active current version.
     */
    public function setCurrentVersion(Skill $skill, SkillVersion $version): RedirectResponse
    {
        Gate::authorize('manage-sops');

        if ($version->skill_id !== $skill->id) {
            abort(404);
        }

        $skill->update(['current_version_id' => $version->id]);

        return back()->with('banner', "Versión v{$version->version_number} establecida como vigente.");
    }

    /**
     * Remove the specified skill.
     */
    public function destroy(Skill $skill): RedirectResponse
    {
        Gate::authorize('manage-sops');

        $skill->delete();

        return redirect()->route('skills.index')->with('banner', 'Skill eliminada.');
    }

    /**
     * Export skill as Markdown with front-matter (Vía B).
     */
    public function exportMarkdown(Skill $skill): StreamedResponse
    {
        Gate::authorize('manage-sops');
        abort_unless((int) $skill->team_id === (int) request()->user()->current_team_id, 404);

        $version = $skill->currentVersion;
        $varsYaml = empty($version?->variables)
            ? '[]'
            : "\n  - " . implode("\n  - ", $version->variables);

        $sections = SkillMarkdownParser::splitSections($version?->instructions ?? '');
        $body = '';
        if (! empty($sections['system_prompt'])) {
            $body .= "## Instrucciones del Sistema\n\n{$sections['system_prompt']}\n\n";
        }
        if (! empty($sections['user_instructions'])) {
            $body .= "## Prompt a Ejecutar\n\n{$sections['user_instructions']}\n\n";
        }
        if ($body === '') {
            $body = "## Instrucciones\n\n{$version?->instructions}\n\n";
        }

        $markdown = <<<MD
---
tipo: skill
nombre: "{$skill->name}"
slug: "{$skill->slug}"
version: {$version?->version_number}
variables: {$varsYaml}
generado: {$version?->created_at?->format('Y-m-d')}
---

{$body}
MD;

        $fileName = "skill-{$skill->slug}-v{$version?->version_number}.md";

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $fileName, ['Content-Type' => 'text/markdown']);
    }

    /**
     * Preview Markdown import and calculate diff without writing to database.
     */
    public function previewImport(Request $request, Skill $skill): JsonResponse
    {
        Gate::authorize('manage-sops');
        abort_unless((int) $skill->team_id === (int) $request->user()->current_team_id, 404);

        $maxBytes = (int) config('skills.import_max_bytes', 204800); // 200 KB por defecto
        $content = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($file->getSize() > $maxBytes) {
                return response()->json([
                    'message' => 'El archivo supera el tamaño máximo permitido de 200 KB.',
                ], 422);
            }
            $content = file_get_contents($file->getRealPath());
        } else {
            $raw = $request->input('content');
            if (empty($raw)) {
                return response()->json([
                    'message' => 'Debes proporcionar un archivo .md o pegar el contenido.',
                ], 422);
            }
            if (strlen($raw) > $maxBytes) {
                return response()->json([
                    'message' => 'El archivo supera el tamaño máximo permitido de 200 KB.',
                ], 422);
            }
            $content = $raw;
        }

        try {
            $parsed = SkillMarkdownParser::parse($content);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // Validación de slug de destino
        $fileSlug = $parsed['front_matter']['slug'] ?? '';
        if ($fileSlug !== $skill->slug) {
            return response()->json([
                'message' => "El archivo corresponde al skill '{$fileSlug}', pero intentas importarlo en '{$skill->slug}'.",
            ], 422);
        }

        // Comprobación de aviso de versión previa
        $fileVersion = isset($parsed['front_matter']['version']) && is_numeric($parsed['front_matter']['version'])
            ? (int) $parsed['front_matter']['version']
            : null;
        $currentVersionNumber = $skill->currentVersion?->version_number ?? 1;
        $warning = null;
        if ($fileVersion !== null && $fileVersion < $currentVersionNumber) {
            $warning = "Aviso: El archivo editado se basa en la versión v{$fileVersion}, pero la versión vigente actual es v{$currentVersionNumber}.";
        }

        // Desglose de secciones vigente vs propuesta
        $currentInstructions = $skill->currentVersion?->instructions ?? '';
        $currentSections = SkillMarkdownParser::splitSections($currentInstructions);

        $proposedSections = [
            'system_prompt' => $parsed['system_prompt'],
            'user_instructions' => $parsed['user_instructions'],
        ];

        // Comparación de variables
        $currentVariables = $skill->currentVersion?->variables ?? [];
        $proposedVariables = VariableResolver::extractVariables($parsed['body']);

        $addedVariables = array_values(array_diff($proposedVariables, $currentVariables));
        $removedVariables = array_values(array_diff($currentVariables, $proposedVariables));

        // Detectar si alguna variable eliminada es usada por SOPs publicados
        $removedVariablesInSops = [];
        if (! empty($removedVariables)) {
            $publishedSops = Sop::where('team_id', $skill->team_id)
                ->where('status', 'published')
                ->whereNotNull('current_version_id')
                ->with('currentVersion')
                ->get(['id', 'title', 'current_version_id']);

            foreach ($removedVariables as $var) {
                $varPattern = '{{' . $var . '}}';
                $matchingSops = [];
                foreach ($publishedSops as $sop) {
                    $blocks = $sop->currentVersion?->blocks['blocks'] ?? [];
                    foreach ($blocks as $block) {
                        $encoded = json_encode($block);
                        if (str_contains($encoded, $varPattern)) {
                            $matchingSops[] = $sop->title;
                            break;
                        }
                    }
                }
                if (! empty($matchingSops)) {
                    $removedVariablesInSops[$var] = array_values(array_unique($matchingSops));
                }
            }
        }

        // Cálculo de diff por líneas
        $systemDiff = LineDiffer::diff($currentSections['system_prompt'], $proposedSections['system_prompt']);
        $userDiff = LineDiffer::diff($currentSections['user_instructions'], $proposedSections['user_instructions']);
        $totalAdded = $systemDiff['added_count'] + $userDiff['added_count'];
        $totalRemoved = $systemDiff['removed_count'] + $userDiff['removed_count'];

        return response()->json([
            'skill_id' => $skill->id,
            'skill_slug' => $skill->slug,
            'base_version_id' => $skill->current_version_id,
            'base_version_number' => $currentVersionNumber,
            'file_version_number' => $fileVersion,
            'warning' => $warning,
            'added_variables' => $addedVariables,
            'removed_variables' => $removedVariables,
            'removed_variables_in_sops' => $removedVariablesInSops,
            'diff' => [
                'total_added' => $totalAdded,
                'total_removed' => $totalRemoved,
                'system_prompt' => $systemDiff,
                'user_instructions' => $userDiff,
            ],
            'proposed' => [
                'system_prompt' => $proposedSections['system_prompt'],
                'user_instructions' => $proposedSections['user_instructions'],
                'raw_body' => $parsed['body'],
                'variables' => $proposedVariables,
            ],
        ]);
    }

    /**
     * Confirm Markdown import and persist the new skill version.
     */
    public function confirmImport(Request $request, Skill $skill): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-sops');
        abort_unless((int) $skill->team_id === (int) $request->user()->current_team_id, 404);

        $validated = $request->validate([
            'base_version_id' => ['nullable', 'integer'],
            'content' => ['required', 'string', 'max:204800'],
            'changelog' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'changelog.required' => 'El campo notas del cambio (changelog) es obligatorio.',
            'changelog.min' => 'El changelog debe tener al menos 3 caracteres.',
        ]);

        // Verificación de concurrencia: rechazar si la versión vigente cambió desde la vista previa
        if (! empty($validated['base_version_id']) && (int) $skill->current_version_id !== (int) $validated['base_version_id']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Este skill cambió desde la vista previa; vuelve a revisarlo.',
                ], 422);
            }
            return back()->withErrors(['content' => 'Este skill cambió desde la vista previa; vuelve a revisarlo.']);
        }

        $instructions = $validated['content'];
        // Si viene con front-matter, extraer solo el cuerpo
        if (preg_match('/^---\s*[\r\n]+(.*?)\s*[\r\n]+---\s*[\r\n]+(.*)$/s', $instructions, $matches)) {
            $instructions = trim($matches[2]);
        }

        $variables = VariableResolver::extractVariables($instructions);
        $nextVersion = ((int) $skill->versions()->max('version_number')) + 1;

        $newVersion = DB::transaction(function () use ($skill, $nextVersion, $instructions, $variables, $validated, $request) {
            $version = SkillVersion::create([
                'skill_id' => $skill->id,
                'version_number' => $nextVersion,
                'instructions' => $instructions,
                'variables' => $variables,
                'source' => 'import_markdown',
                'changelog' => $validated['changelog'],
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            $skill->update(['current_version_id' => $version->id]);

            activity()
                ->performedOn($skill)
                ->causedBy($request->user())
                ->withProperties([
                    'version_number' => $nextVersion,
                    'source' => 'import_markdown',
                    'changelog' => $validated['changelog'],
                ])
                ->log("Skill importado desde Markdown generando versión v{$nextVersion}");

            return $version;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'version' => $newVersion,
                'message' => "Versión v{$nextVersion} importada con éxito desde Markdown.",
            ]);
        }

        return redirect()->route('skills.show', $skill->id)
            ->with('banner', "Versión v{$nextVersion} importada con éxito desde Markdown.");
    }

    /**
     * Import markdown fallback method.
     */
    public function importMarkdown(Request $request, Skill $skill): RedirectResponse
    {
        return $this->confirmImport($request, $skill);
    }
}
