<?php

namespace Tests\Feature;

use App\Actions\Sop\AdvanceRun;
use App\Actions\Sop\StartSopRun;
use App\Events\ClientCreated;
use App\Events\RunCompleted;
use App\Listeners\ProcessAutomationTriggers;
use App\Models\AutomationTrigger;
use App\Models\Client;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AutomationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_client_creation_dispatches_client_created_event(): void
    {
        Event::fake([ClientCreated::class]);

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        session(['current_team_id' => $admin->currentTeam->id]);

        $client = Client::create([
            'team_id' => $admin->currentTeam->id,
            'name' => 'Cliente Trigger Test',
            'slug' => 'cliente-trigger-test',
            'status' => 'active',
        ]);

        Event::assertDispatched(ClientCreated::class, function ($e) use ($client) {
            return $e->client->id === $client->id;
        });
    }

    public function test_automation_trigger_creates_sop_run_when_client_created(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;
        session(['current_team_id' => $team->id]);

        // Crear SOP con versión publicada
        $sop = Sop::create([
            'team_id' => $team->id,
            'title' => 'SOP Onboarding Automático',
            'slug' => 'sop-onboarding-auto',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);

        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => [
                'schema_version' => 1,
                'blocks' => [
                    [
                        'id' => 'b1',
                        'type' => 'input',
                        'props' => ['key' => 'nombre', 'label' => 'Nombre', 'field' => 'text', 'filled_by' => 'client'],
                    ],
                ],
            ],
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);

        $sop->current_version_id = $version->id;
        $sop->save();

        // Crear trigger activo
        $trigger = AutomationTrigger::create([
            'team_id' => $team->id,
            'event' => 'client.created',
            'sop_id' => $sop->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // Crear cliente
        $client = Client::create([
            'team_id' => $team->id,
            'name' => 'Nuevo Cliente Acrobático',
            'slug' => 'nuevo-cliente-acrobatico',
            'status' => 'active',
        ]);

        // Ejecutar listener
        $listener = new ProcessAutomationTriggers(app(StartSopRun::class));
        $listener->handle(new ClientCreated($client));

        // Verificar que se creó la corrida
        $this->assertDatabaseHas('sop_runs', [
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'sop_version_id' => $version->id,
            'client_id' => $client->id,
        ]);

        $run = SopRun::where('client_id', $client->id)->first();
        $this->assertStringContainsString('[Automático]', $run->title);
        $this->assertStringContainsString('Nuevo Cliente Acrobático', $run->title);
    }

    public function test_inactive_trigger_does_not_create_sop_run(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;

        $sop = Sop::create([
            'team_id' => $team->id,
            'title' => 'SOP Inactivo',
            'slug' => 'sop-inactivo',
            'status' => 'published',
        ]);

        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => []],
            'published_at' => now(),
        ]);
        $sop->current_version_id = $version->id;
        $sop->save();

        // Trigger inactivo
        AutomationTrigger::create([
            'team_id' => $team->id,
            'event' => 'client.created',
            'sop_id' => $sop->id,
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $client = Client::create([
            'team_id' => $team->id,
            'name' => 'Cliente Sin Trigger',
            'slug' => 'cliente-sin-trigger',
            'status' => 'active',
        ]);

        $listener = new ProcessAutomationTriggers(app(StartSopRun::class));
        $listener->handle(new ClientCreated($client));

        $this->assertDatabaseMissing('sop_runs', [
            'client_id' => $client->id,
        ]);
    }

    public function test_run_completed_event_triggers_followup_sop(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;
        session(['current_team_id' => $team->id]);

        $sop1 = Sop::create(['team_id' => $team->id, 'title' => 'SOP 1', 'slug' => 'sop-1', 'status' => 'published']);
        $v1 = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop1->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => [
                ['id' => 'b1', 'type' => 'input', 'props' => ['key' => 'd1', 'field' => 'text']],
            ]],
            'published_at' => now(),
        ]);
        $sop1->current_version_id = $v1->id;
        $sop1->save();

        $sop2 = Sop::create(['team_id' => $team->id, 'title' => 'SOP Seguimiento', 'slug' => 'sop-seguimiento', 'status' => 'published']);
        $v2 = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop2->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => []],
            'published_at' => now(),
        ]);
        $sop2->current_version_id = $v2->id;
        $sop2->save();

        // Trigger para run.completed -> sop2
        AutomationTrigger::create([
            'team_id' => $team->id,
            'event' => 'run.completed',
            'sop_id' => $sop2->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $client = Client::create(['team_id' => $team->id, 'name' => 'Cliente Flujo', 'slug' => 'cliente-flujo']);

        $startAction = app(StartSopRun::class);
        $run1 = $startAction->execute($admin, $sop1, [
            'sop_version_id' => $v1->id,
            'client_id' => $client->id,
            'title' => 'Ejecución Inicial',
        ]);

        $step = $run1->steps->firstWhere('block_id', 'b1');

        // Completar el paso
        Event::fake([RunCompleted::class]);
        $advanceAction = app(AdvanceRun::class);
        $advanceAction->execute($run1, $step, ['value' => 'Dato']);

        Event::assertDispatched(RunCompleted::class);
    }

    public function test_admin_can_manage_automation_triggers_via_controller(): void
    {
        $admin = User::factory()->withPersonalTeam()->create([
            'two_factor_secret' => 'dummy',
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('admin');
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'SOP Controller Test', 'slug' => 'sop-ctrl-test', 'status' => 'published']);
        $v = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => []],
            'published_at' => now(),
        ]);
        $sop->current_version_id = $v->id;
        $sop->save();

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        // Index
        $response = $this->get(route('admin.automation-triggers.index'));
        $response->assertStatus(200);

        // Store
        $storeResponse = $this->post(route('admin.automation-triggers.store'), [
            'event' => 'client.created',
            'sop_id' => $sop->id,
            'is_active' => true,
        ]);
        $storeResponse->assertRedirect();

        $trigger = AutomationTrigger::where('sop_id', $sop->id)->first();
        $this->assertNotNull($trigger);
        $this->assertTrue($trigger->is_active);

        // Update (Toggle)
        $updateResponse = $this->put(route('admin.automation-triggers.update', ['automation_trigger' => $trigger->id]), [
            'is_active' => false,
        ]);
        $updateResponse->assertRedirect();
        $this->assertFalse($trigger->fresh()->is_active);

        // Destroy
        $destroyResponse = $this->delete(route('admin.automation-triggers.destroy', ['automation_trigger' => $trigger->id]));
        $destroyResponse->assertRedirect();
        $this->assertDatabaseMissing('automation_triggers', ['id' => $trigger->id]);
    }
}
