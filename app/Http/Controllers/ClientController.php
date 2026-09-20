<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of clients for the active team.
     */
    public function index(Request $request): Response
    {
        $clients = Client::withCount(['users', 'runs', 'invitations'])
            ->latest()
            ->paginate(15)
            ->through(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'contact_email' => $client->contact_email,
                'status' => $client->status,
                'users_count' => $client->users_count,
                'runs_count' => $client->runs_count,
                'invitations_count' => $client->invitations_count,
                'created_at' => $client->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,paused,archived'],
        ]);

        $slug = Str::slug($validated['name']);
        // Asegurar unicidad del slug en el equipo
        $baseSlug = $slug;
        $counter = 1;
        while (Client::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        Client::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'contact_email' => $validated['contact_email'] ?? null,
            'status' => $validated['status'],
        ]);

        return back()->with('banner', 'Cliente creado exitosamente. Se evaluaron las automatizaciones configuradas.');
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return back()->with('banner', 'Cliente eliminado.');
    }
}
