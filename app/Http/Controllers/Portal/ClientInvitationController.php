<?php

namespace App\Http\Controllers\Portal;

use App\Actions\AcceptClientInvitation;
use App\Actions\InviteClientUser;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ClientInvitationController extends Controller
{
    /**
     * Show invitation acceptance page.
     */
    public function show(string $token): Response
    {
        $invitation = ClientInvitation::with('client')->where('token', $token)->first();

        if (! $invitation) {
            return Inertia::render('Portal/AcceptInvitation', [
                'error' => 'El enlace de invitación no es válido o no existe.',
                'invitation' => null,
            ]);
        }

        if ($invitation->isAccepted()) {
            return Inertia::render('Portal/AcceptInvitation', [
                'error' => 'Esta invitación ya ha sido aceptada previamente.',
                'invitation' => null,
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Portal/AcceptInvitation', [
                'error' => 'El enlace de invitación ha expirado. Solicita a la agencia un nuevo enlace.',
                'invitation' => null,
            ]);
        }

        return Inertia::render('Portal/AcceptInvitation', [
            'error' => null,
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'client_name' => $invitation->client->name ?? 'Organización',
            ],
        ]);
    }

    /**
     * Process accepting the invitation.
     */
    public function accept(Request $request, string $token, AcceptClientInvitation $action): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = $action->execute($token, $request->string('name'), $request->string('password'));
            auth()->login($user);
            $request->session()->regenerate();

            return redirect()->route('portal.runs.index')->with('banner', '¡Bienvenido al portal de clientes!');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a new invitation sent by agency staff.
     */
    public function store(Request $request, Client $client, InviteClientUser $action): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'in:owner,collaborator'],
        ]);

        $action->execute(
            $client,
            $request->string('email'),
            $request->input('role', 'collaborator'),
            $request->user()
        );

        return back()->with('banner', 'Invitación enviada exitosamente por correo electrónico.');
    }
}
