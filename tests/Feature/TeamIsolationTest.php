<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_a_cannot_read_update_or_delete_team_b_clients(): void
    {
        // 1. Crear dos usuarios con sus respectivos equipos
        $userA = User::factory()->withPersonalTeam()->create();
        $teamA = $userA->currentTeam;

        $userB = User::factory()->withPersonalTeam()->create();
        $teamB = $userB->currentTeam;

        // 2. Crear cliente para el Equipo A con sesión/autenticación de Equipo A
        $this->actingAs($userA);
        session(['current_team_id' => $teamA->id]);
        $clientA = Client::create([
            'name' => 'Cliente Equipo A',
            'slug' => 'cliente-equipo-a',
            'contact_email' => 'clientea@empresa.com',
            'status' => 'active',
        ]);

        $this->assertEquals($teamA->id, $clientA->team_id);

        // 3. Crear cliente para el Equipo B con sesión/autenticación de Equipo B
        $this->actingAs($userB);
        session(['current_team_id' => $teamB->id]);
        $clientB = Client::create([
            'name' => 'Cliente Equipo B',
            'slug' => 'cliente-equipo-b',
            'contact_email' => 'clienteb@empresa.com',
            'status' => 'active',
        ]);

        $this->assertEquals($teamB->id, $clientB->team_id);

        // 4. Conectarse como Equipo A y verificar aislamiento
        $this->actingAs($userA);
        session(['current_team_id' => $teamA->id]);

        // LECTURA: El Equipo A solo debe ver a su propio cliente
        $clients = Client::all();
        $this->assertCount(1, $clients);
        $this->assertTrue($clients->contains($clientA));
        $this->assertFalse($clients->contains($clientB));

        // Intento de lectura directa por ID del cliente del Equipo B
        $this->assertNull(Client::find($clientB->id));

        // Intento de lectura directa por slug
        $this->assertNull(Client::where('slug', 'cliente-equipo-b')->first());

        // ACTUALIZACIÓN: El Equipo A no puede actualizar el cliente del Equipo B
        $affectedRows = Client::where('id', $clientB->id)->update(['name' => 'Hackeado por Equipo A']);
        $this->assertEquals(0, $affectedRows);

        // Verificar en BD sin scope que el cliente B sigue intacto
        $clientBFresh = Client::withoutGlobalScope('team')->find($clientB->id);
        $this->assertEquals('Cliente Equipo B', $clientBFresh->name);

        // BORRADO: El Equipo A no puede borrar el cliente del Equipo B
        $deletedRows = Client::where('id', $clientB->id)->delete();
        $this->assertEquals(0, $deletedRows);

        $this->assertNull($clientBFresh->fresh()->deleted_at);

        // 5. Conectarse como Equipo B y verificar aislamiento inverso
        $this->actingAs($userB);
        session(['current_team_id' => $teamB->id]);

        // LECTURA: El Equipo B solo debe ver al cliente B
        $clientsB = Client::all();
        $this->assertCount(1, $clientsB);
        $this->assertTrue($clientsB->contains($clientB));
        $this->assertFalse($clientsB->contains($clientA));
        $this->assertNull(Client::find($clientA->id));
    }
}