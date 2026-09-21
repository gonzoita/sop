<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillVersion;
use App\Support\Sop\VariableResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
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

        $version = $skill->currentVersion;
        $varsYaml = empty($version?->variables)
            ? '[]'
            : "\n  - " . implode("\n  - ", $version->variables);

        $markdown = <<<MD
---
tipo: skill
nombre: "{$skill->name}"
slug: "{$skill->slug}"
version: {$version?->version_number}
variables: {$varsYaml}
generado: {$version?->created_at?->format('Y-m-d')}
---

## Instrucciones

{$version?->instructions}

MD;

        $fileName = "skill-{$skill->slug}-v{$version?->version_number}.md";

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $fileName, ['Content-Type' => 'text/markdown']);
    }

    /**
     * Import a new version from Markdown with front-matter (Vía B).
     */
    public function importMarkdown(Request $request, Skill $skill): RedirectResponse
    {
        Gate::authorize('manage-sops');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'changelog' => ['nullable', 'string', 'max:500'],
        ]);

        $content = $validated['content'];

        // Extraer instrucciones (tras el front-matter si existe)
        $instructions = $content;
        if (preg_match('/^---\s*[\r\n]+(.*?)\s*[\r\n]+---\s*[\r\n]+(.*)$/s', $content, $matches)) {
            $instructions = trim($matches[2]);
            // Opcional: remover encabezado ## Instrucciones
            $instructions = preg_replace('/^##\s*Instrucciones\s*[\r\n]+/i', '', $instructions);
        }

        $variables = VariableResolver::extractVariables($instructions);
        $nextVersion = ((int) $skill->versions()->max('version_number')) + 1;

        DB::transaction(function () use ($skill, $nextVersion, $instructions, $variables, $validated, $request) {
            $version = SkillVersion::create([
                'skill_id' => $skill->id,
                'version_number' => $nextVersion,
                'instructions' => $instructions,
                'variables' => $variables,
                'source' => 'import_markdown',
                'changelog' => $validated['changelog'] ?? 'Reimportado desde Markdown externo',
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            $skill->update(['current_version_id' => $version->id]);
        });

        return back()->with('banner', "Versión v{$nextVersion} importada con éxito desde Markdown.");
    }
}
