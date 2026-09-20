<?php

namespace App\Http\Controllers;

use App\Actions\Sop\CreateSop;
use App\Actions\Sop\DuplicateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\SaveSopDraft;
use App\Exceptions\SopValidationException;
use App\Models\Sop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SopController extends Controller
{
    /**
     * Display a listing of the SOPs.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Sop::class);

        $query = Sop::query()
            ->with(['currentVersion', 'creator'])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sops = $query->paginate(12)->withQueryString();

        $categories = Sop::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('Sops/Index', [
            'sops' => $sops,
            'filters' => $request->only(['search', 'category', 'status']),
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created SOP in storage.
     */
    public function store(Request $request, CreateSop $createSop): RedirectResponse
    {
        Gate::authorize('create', Sop::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_template' => ['boolean'],
        ]);

        $sop = $createSop->execute($request->user(), $validated);

        return redirect()->route('sops.edit', $sop->id)
            ->with('success', 'SOP creado exitosamente.');
    }

    /**
     * Show the form for editing the specified SOP.
     */
    public function edit(Sop $sop): Response
    {
        Gate::authorize('update', $sop);

        $sop->load(['currentVersion', 'versions.creator']);

        // Find active draft or fallback to latest version blocks
        $draft = $sop->versions()
            ->whereNull('published_at')
            ->latest('version_number')
            ->first();

        $latestPublished = $sop->currentVersion;

        $initialBlocks = $draft
            ? $draft->blocks
            : ($latestPublished ? $latestPublished->blocks : ['schema_version' => 1, 'blocks' => []]);

        return Inertia::render('Sops/Edit', [
            'sop' => $sop,
            'activeDraft' => $draft,
            'initialBlocks' => $initialBlocks,
            'versionsHistory' => $sop->versions->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'changelog' => $v->changelog,
                'published_at' => $v->published_at?->toIso8601String(),
                'creator' => $v->creator?->name,
                'is_draft' => $v->isDraft(),
            ]),
        ]);
    }

    /**
     * Update the draft blocks of the specified SOP (Autosave).
     */
    public function saveDraft(Request $request, Sop $sop, SaveSopDraft $saveSopDraft): JsonResponse
    {
        Gate::authorize('update', $sop);

        $validated = $request->validate([
            'blocks' => ['required', 'array'],
            'changelog' => ['nullable', 'string', 'max:500'],
        ]);

        $draft = $saveSopDraft->execute(
            $sop,
            $validated['blocks'],
            $request->user(),
            $validated['changelog'] ?? null
        );

        return response()->json([
            'success' => true,
            'version_id' => $draft->id,
            'version_number' => $draft->version_number,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish the specified SOP draft version.
     */
    public function publish(Request $request, Sop $sop, SaveSopDraft $saveSopDraft, PublishSopVersion $publishSopVersion): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $sop);

        $validated = $request->validate([
            'blocks' => ['nullable', 'array'],
            'changelog' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            // If blocks are provided in the publish request, save draft first
            if (! empty($validated['blocks'])) {
                $saveSopDraft->execute($sop, $validated['blocks'], $request->user());
            }

            $published = $publishSopVersion->execute(
                $sop,
                null,
                $validated['changelog'] ?? null,
                $request->user()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'version_number' => $published->version_number,
                    'published_at' => $published->published_at->toIso8601String(),
                    'message' => "Versión {$published->version_number} publicada exitosamente.",
                ]);
            }

            return back()->with('success', "Versión {$published->version_number} publicada exitosamente.");
        } catch (SopValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            return back()->withErrors(['blocks' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Duplicate the specified SOP.
     */
    public function duplicate(Request $request, Sop $sop, DuplicateSop $duplicateSop): RedirectResponse
    {
        Gate::authorize('create', Sop::class);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $newSop = $duplicateSop->execute($sop, $request->user(), $validated['title'] ?? null);

        return redirect()->route('sops.edit', $newSop->id)
            ->with('success', 'SOP duplicado exitosamente.');
    }

    /**
     * Remove the specified SOP from storage.
     */
    public function destroy(Sop $sop): RedirectResponse
    {
        Gate::authorize('delete', $sop);

        $sop->delete();

        return redirect()->route('sops.index')
            ->with('success', 'SOP eliminado exitosamente.');
    }
}
