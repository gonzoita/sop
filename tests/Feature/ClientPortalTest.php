<?php

namespace Tests\Feature;

use App\Actions\AcceptClientInvitation;
use App\Actions\InviteClientUser;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\ClientInvitation;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopVersion;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ClientInvitationNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_client_invitation_can_be_created_and_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->currentTeam;

        $client = Client::create([
            'team_id' => $team->id,
            'name' => 'Cliente Alpha',
            'slug' => 'cliente-alpha',
            'contact_email' => 'alpha@cliente.com',
            'status' => 'active',
        ]);

        $action = new InviteClientUser();
        $invitation = $action->execute($client, 'contacto@alpha.com', 'owner', $admin);

        $this->assertDatabaseHas('client_invitations', [
            'id' => $invitation->id,
            'email' => 'contacto@alpha.com',
            'role' => 'owner',
        ]);

        $this->assertTrue($invitation->isValid());
        $this->assertFalse($invitation->isExpired());

        Notification::assertSentOnDemand(ClientInvitationNotification::class);
    }

    public function test_client_invitation_can_be_accepted(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;

        $client = Client::create([
            'team_id' => $team->id,
            'name' => 'Cliente Beta',
            'slug' => 'cliente-beta',
            'contact_email' => 'beta@cliente.com',
            'status' => 'active',
        ]);

        $invitation = ClientInvitation::create([
            'team_id' => $team->id,
            'client_id' => $client->id,
            'email' => 'user@beta.com',
            'role' => 'owner',
            'token' => 'test-valid-token-12345678901234567890123456789012',
            'expires_at' => now()->addHours(48),
        ]);

        $response = $this->post(route('portal.invitations.process', ['token' => $invitation->token]), [
            'name' => 'Usuario Beta',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('portal.runs.index'));

        $this->assertAuthenticated();
        $user = auth()->user();
        $this->assertEquals('user@beta.com', $user->email);
        $this->assertTrue($user->hasRole('cliente'));

        // Verificar vinculación en client_user
        $this->assertDatabaseHas('client_user', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Verificar que la invitación quedó marcada como aceptada
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_client_cannot_access_agency_internal_routes(): void
    {
        $clientUser = User::factory()->create();
        $clientUser->assignRole('cliente');

        $this->actingAs($clientUser);

        // Dashboard redirige a portal
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('portal.runs.index'));

        // SOPs list niega acceso con 403 por Policy
        $responseSops = $this->get('/sops');
        $responseSops->assertStatus(403);

        // Runs list de agencia niega acceso con 403 por Policy
        $responseRuns = $this->get('/runs');
        $responseRuns->assertStatus(403);
    }

    public function test_client_can_only_see_their_own_runs(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;

        $clientA = Client::create(['team_id' => $team->id, 'name' => 'Cliente A', 'slug' => 'cliente-a']);
        $clientB = Client::create(['team_id' => $team->id, 'name' => 'Cliente B', 'slug' => 'cliente-b']);

        $userClientA = User::factory()->create();
        $userClientA->assignRole('cliente');
        $clientA->users()->attach($userClientA->id, ['role' => 'collaborator', 'accepted_at' => now()]);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'SOP General', 'slug' => 'sop-general']);
        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => []],
        ]);

        $runA = SopRun::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'sop_version_id' => $version->id,
            'client_id' => $clientA->id,
            'title' => 'Ejecución para Cliente A',
            'status' => 'in_progress',
        ]);

        $runB = SopRun::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'sop_version_id' => $version->id,
            'client_id' => $clientB->id,
            'title' => 'Ejecución para Cliente B',
            'status' => 'in_progress',
        ]);

        $this->actingAs($userClientA);

        $response = $this->get(route('portal.runs.index'));
        $response->assertStatus(200);

        // Intento de acceder a la corrida del Cliente B
        $forbiddenResponse = $this->get(route('portal.runs.show', ['run' => $runB->id]));
        $forbiddenResponse->assertStatus(403);
    }

    public function test_client_show_only_includes_client_blocks(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;

        $client = Client::create(['team_id' => $team->id, 'name' => 'Cliente VIP', 'slug' => 'cliente-vip']);
        $userClient = User::factory()->create();
        $userClient->assignRole('cliente');
        $client->users()->attach($userClient->id, ['role' => 'owner', 'accepted_at' => now()]);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'Onboarding Completo', 'slug' => 'onboarding-completo']);
        $blocks = [
            [
                'id' => 'b1_team',
                'type' => 'input',
                'props' => ['key' => 'id_interno', 'label' => 'ID Interno de Agencia', 'field' => 'text', 'filled_by' => 'team'],
            ],
            [
                'id' => 'b2_client',
                'type' => 'input',
                'props' => ['key' => 'url_web', 'label' => 'Sitio Web de la Marca', 'field' => 'url', 'filled_by' => 'client'],
            ],
            [
                'id' => 'b3_ai',
                'type' => 'ai_task',
                'props' => [
                    'skill_slug' => 'analisis-marca',
                    'inputs' => ['web' => '{{url_web}}'],
                    'output_key' => 'analisis',
                ],
            ],
        ];

        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => $blocks],
        ]);

        $admin->current_team_id = $team->id;
        $admin->save();

        $startAction = new StartSopRun();
        $run = $startAction->execute($admin, $sop, [
            'sop_version_id' => $version->id,
            'title' => 'Ejecución Onboarding VIP',
            'client_id' => $client->id,
        ]);

        $this->actingAs($userClient);

        $response = $this->get(route('portal.runs.show', ['run' => $run->id]));
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Runs/Show')
            ->has('blocks', 1)
            ->where('blocks.0.id', 'b2_client')
            ->where('blocks.0.props.key', 'url_web')
        );
    }

    public function test_client_can_advance_step_and_upload_file(): void
    {
        Storage::fake('local');

        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;

        $client = Client::create(['team_id' => $team->id, 'name' => 'Cliente Archivos', 'slug' => 'cliente-archivos']);
        $userClient = User::factory()->create();
        $userClient->assignRole('cliente');
        $client->users()->attach($userClient->id, ['role' => 'owner', 'accepted_at' => now()]);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'Recepción de Assets', 'slug' => 'recepcion-assets']);
        $blocks = [
            [
                'id' => 'b_file',
                'type' => 'input',
                'props' => ['key' => 'logo_vectorial', 'label' => 'Logotipo Vectorial', 'field' => 'file', 'filled_by' => 'client'],
            ],
        ];

        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => $blocks],
        ]);

        $admin->current_team_id = $team->id;
        $admin->save();

        $startAction = new StartSopRun();
        $run = $startAction->execute($admin, $sop, [
            'sop_version_id' => $version->id,
            'title' => 'Assets de Cliente',
            'client_id' => $client->id,
        ]);
        $step = $run->steps->firstWhere('block_id', 'b_file');

        $this->actingAs($userClient);

        // Subida de archivo seguro
        $file = UploadedFile::fake()->create('logo_cliente.pdf', 500, 'application/pdf');

        $uploadResponse = $this->postJson(route('portal.runs.upload', ['run' => $run->id]), [
            'file' => $file,
            'block_id' => 'b_file',
        ]);

        $uploadResponse->assertStatus(200);
        $fileId = $uploadResponse->json('id');
        $filePath = $uploadResponse->json('file_path');

        $this->assertDatabaseHas('client_files', [
            'id' => $fileId,
            'sop_run_id' => $run->id,
            'original_name' => 'logo_cliente.pdf',
        ]);

        // Descarga de archivo autorizado
        $downloadResponse = $this->get(route('portal.runs.files.download', ['run' => $run->id, 'file' => $fileId]));
        $downloadResponse->assertStatus(200);

        // Avanzar el paso con el valor
        $advanceResponse = $this->post(route('portal.runs.steps.advance', ['run' => $run->id, 'step' => $step->id]), [
            'value' => ['name' => 'logo_cliente.pdf', 'file_id' => $fileId],
        ]);

        $advanceResponse->assertRedirect();
        $this->assertEquals(['name' => 'logo_cliente.pdf', 'file_id' => $fileId], $run->fresh()->inputs['logo_vectorial']);
    }
}
