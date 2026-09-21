<?php

namespace Tests\Feature;

use App\Actions\Sop\CreateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\SaveSopDraft;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createTeamUser(string $role = 'admin'): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->current_team_id = $user->currentTeam->id;
        $user->save();
        $user->assignRole($role);

        return $user;
    }

    protected function createClientUser(Client $client): User
    {
        $clientUser = User::factory()->create();
        $clientUser->assignRole('cliente');
        $client->users()->attach($clientUser->id, [
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        return $clientUser;
    }

    protected function setupRunWithClient(): array
    {
        $admin = $this->createTeamUser('admin');
        $team = $admin->currentTeam;

        $client = Client::create([
            'team_id' => $team->id,
            'name' => 'Empresa Cliente',
            'slug' => 'empresa-cliente',
            'contact_email' => 'info@empresa.com',
            'status' => 'active',
        ]);

        $sop = app(CreateSop::class)->execute($admin, [
            'title' => 'Estrategia de Contenidos',
        ]);

        app(SaveSopDraft::class)->execute($sop, [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'inp1',
                    'type' => 'input',
                    'props' => ['key' => 'objetivo', 'label' => 'Objetivo Principal'],
                ],
                [
                    'id' => 'txt1',
                    'type' => 'text',
                    'props' => ['html' => '<p>El objetivo fijado es: {{objetivo}}</p>'],
                ],
            ],
        ]);

        $version = app(PublishSopVersion::class)->execute($sop, null, null, $admin);

        $run = app(StartSopRun::class)->execute($admin, $sop, [
            'sop_version_id' => $version->id,
            'title' => 'Campaña Q1 2026',
            'client_id' => $client->id,
            'starter_id' => $admin->id,
            'inputs' => [
                'objetivo' => 'Aumentar conversiones un 25%',
            ],
        ]);

        return [$admin, $client, $run];
    }

    public function test_team_member_can_preview_deliverable_markdown(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $this->actingAs($admin);

        $response = $this->getJson(route('runs.deliverable.preview', $run->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['title', 'markdown']);
        $this->assertStringContainsString('Aumentar conversiones un 25%', $response->json('markdown'));
    }

    public function test_team_member_can_publish_deliverable_to_client(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $this->actingAs($admin);

        $response = $this->post(route('runs.client-documents.store', $run->id), [
            'title' => 'Entregable Final de Estrategia Q1',
            'markdown' => "# Entregable Final\n\nContenido verificado para el cliente.",
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_documents', [
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Entregable Final de Estrategia Q1',
            'markdown' => "# Entregable Final\n\nContenido verificado para el cliente.",
            'published_by' => $admin->id,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client_documents',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_editing_execution_does_not_affect_published_document(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $this->actingAs($admin);

        $this->post(route('runs.client-documents.store', $run->id), [
            'title' => 'Documento Inmutable',
            'markdown' => 'Versión estática publicada el día 1.',
        ]);

        $document = ClientDocument::where('sop_run_id', $run->id)->first();
        $this->assertNotNull($document);

        // Modificamos la ejecución (cambiando inputs y título)
        $run->update([
            'title' => 'Título alterado post-publicación',
            'inputs' => ['objetivo' => 'Objetivo completamente diferente'],
        ]);

        // El documento del cliente debe permanecer inmutable
        $document->refresh();
        $this->assertEquals('Versión estática publicada el día 1.', $document->markdown);
        $this->assertEquals('Documento Inmutable', $document->title);
    }

    public function test_team_member_can_revoke_document(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Documento a Revocar',
            'markdown' => 'Contenido confidencial o con errores.',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $this->assertFalse($document->isRevoked());

        $this->actingAs($admin);

        $response = $this->patch(route('client-documents.revoke', $document->id));
        $response->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertTrue($document->isRevoked());
        $this->assertNotNull($document->revoked_at);
        $this->assertEquals('revoked', $document->status);
    }

    public function test_client_can_see_published_documents_in_portal(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Estrategia Aprobada',
            'markdown' => 'Plan de acción para el cliente.',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $clientUser = $this->createClientUser($client);

        $this->actingAs($clientUser);

        $response = $this->get(route('portal.documents.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Documents/Index')
            ->has('documents', 1)
            ->where('documents.0.id', $document->id)
            ->where('documents.0.title', 'Estrategia Aprobada')
        );
    }

    public function test_client_cannot_see_revoked_documents_in_portal(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Documento Retirado',
            'markdown' => 'Ya no debe verse.',
            'published_by' => $admin->id,
            'published_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);

        $clientUser = $this->createClientUser($client);

        $this->actingAs($clientUser);

        $response = $this->get(route('portal.documents.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Documents/Index')
            ->has('documents', 0)
        );
    }

    public function test_client_cannot_see_documents_of_other_clients(): void
    {
        [$admin, $clientA, $runA] = $this->setupRunWithClient();

        $clientB = Client::create([
            'team_id' => $admin->current_team_id,
            'name' => 'Cliente B',
            'slug' => 'cliente-b',
        ]);

        $docB = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $clientB->id,
            'sop_run_id' => $runA->id,
            'title' => 'Documento de Cliente B',
            'markdown' => 'Contenido privado de B.',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $clientUserA = $this->createClientUser($clientA);

        $this->actingAs($clientUserA);

        $response = $this->get(route('portal.documents.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Documents/Index')
            ->has('documents', 0)
        );
    }

    public function test_client_accessing_revoked_or_other_client_document_receives_404(): void
    {
        [$admin, $clientA, $runA] = $this->setupRunWithClient();

        $clientB = Client::create([
            'team_id' => $admin->current_team_id,
            'name' => 'Cliente B',
            'slug' => 'cliente-b',
        ]);

        // Doc de otro cliente
        $docB = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $clientB->id,
            'sop_run_id' => $runA->id,
            'title' => 'Documento Ajeno',
            'markdown' => 'Privado',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        // Doc propio pero revocado
        $revokedDocA = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $clientA->id,
            'sop_run_id' => $runA->id,
            'title' => 'Documento Revocado Propio',
            'markdown' => 'Revocado',
            'published_by' => $admin->id,
            'published_at' => now(),
            'revoked_at' => now(),
        ]);

        $clientUserA = $this->createClientUser($clientA);
        $this->actingAs($clientUserA);

        // Intento de ver doc de otro cliente: DEBE dar 404 (no 403 para no enumerar)
        $this->get(route('portal.documents.show', $docB->id))->assertStatus(404);
        $this->get(route('portal.documents.download.md', $docB->id))->assertStatus(404);
        $this->get(route('portal.documents.download.pdf', $docB->id))->assertStatus(404);

        // Intento de ver doc revocado propio: DEBE dar 404
        $this->get(route('portal.documents.show', $revokedDocA->id))->assertStatus(404);
        $this->get(route('portal.documents.download.md', $revokedDocA->id))->assertStatus(404);
        $this->get(route('portal.documents.download.pdf', $revokedDocA->id))->assertStatus(404);
    }

    public function test_client_can_download_markdown(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Entregable de Campaña',
            'markdown' => "# Estrategia de Marketing\n\nDetalles del plan.",
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $clientUser = $this->createClientUser($client);
        $this->actingAs($clientUser);

        $response = $this->get(route('portal.documents.download.md', $document->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('entregable-de-campana.md', (string) $response->headers->get('Content-Disposition'));
        $this->assertEquals("# Estrategia de Marketing\n\nDetalles del plan.", $response->getContent());
    }

    public function test_client_can_download_pdf(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Reporte Final Q1',
            'markdown' => "# Reporte Final\n\nTexto de prueba para PDF.",
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $clientUser = $this->createClientUser($client);
        $this->actingAs($clientUser);

        $response = $this->get(route('portal.documents.download.pdf', $document->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('reporte-final-q1.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_client_role_cannot_publish_or_revoke_documents(): void
    {
        [$admin, $client, $run] = $this->setupRunWithClient();

        $document = ClientDocument::create([
            'team_id' => $admin->current_team_id,
            'client_id' => $client->id,
            'sop_run_id' => $run->id,
            'title' => 'Doc de Prueba',
            'markdown' => 'Texto',
            'published_by' => $admin->id,
            'published_at' => now(),
        ]);

        $clientUser = $this->createClientUser($client);
        $this->actingAs($clientUser);

        // Preview bloqueado
        $this->getJson(route('runs.deliverable.preview', $run->id))->assertStatus(403);

        // Publicar bloqueado
        $this->post(route('runs.client-documents.store', $run->id), [
            'title' => 'Hack',
            'markdown' => 'Inyección',
        ])->assertStatus(403);

        // Revocar bloqueado
        $this->patch(route('client-documents.revoke', $document->id))->assertStatus(403);
    }
}
