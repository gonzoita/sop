<?php

namespace Tests\Feature;

use App\Actions\Sop\CreateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\SaveSopDraft;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SopRunControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createUserWithTeam(string $role = 'admin'): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->current_team_id = $user->currentTeam->id;
        $user->save();
        $user->assignRole($role);

        return $user;
    }

    protected function createPublishedSop(User $user): Sop
    {
        $sop = app(CreateSop::class)->execute($user, ['title' => 'SOP Controller Test']);

        app(SaveSopDraft::class)->execute($sop, [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'input',
                    'props' => ['key' => 'marca', 'label' => 'Marca', 'field' => 'text'],
                ],
                [
                    'id' => 'b2',
                    'type' => 'checklist',
                    'props' => [
                        'items' => [
                            ['id' => 'i1', 'text' => 'Item 1', 'required' => true],
                        ],
                    ],
                ],
            ],
        ], $user);

        app(PublishSopVersion::class)->execute($sop, null, 'Publicación inicial', $user);

        return $sop->fresh(['currentVersion']);
    }

    public function test_runs_index_is_accessible_and_renders_inertia_page(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->get(route('runs.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Runs/Index')
            ->has('runs')
            ->has('clients')
            ->has('teamMembers')
            ->has('availableSops')
        );
    }

    public function test_cliente_cannot_access_runs_index(): void
    {
        $cliente = $this->createUserWithTeam('cliente');
        $this->actingAs($cliente);
        session(['current_team_id' => $cliente->current_team_id]);

        $response = $this->get(route('runs.index'));
        $response->assertStatus(403);
    }

    public function test_store_creates_run_and_redirects_to_show(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = $this->createPublishedSop($admin);
        $client = Client::create([
            'team_id' => $admin->current_team_id,
            'name' => 'Cliente Test',
            'slug' => 'cliente-test',
            'status' => 'active',
        ]);

        $response = $this->post(route('runs.store'), [
            'sop_id' => $sop->id,
            'client_id' => $client->id,
            'title' => 'Ejecución Prueba Especial',
        ]);

        $run = SopRun::where('title', 'Ejecución Prueba Especial')->first();
        $this->assertNotNull($run);
        $this->assertEquals($sop->id, $run->sop_id);
        $this->assertEquals($client->id, $run->client_id);
        $response->assertRedirect(route('runs.show', $run->id));
    }

    public function test_show_loads_run_execution_screen(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = $this->createPublishedSop($admin);
        $run = app(StartSopRun::class)->execute($admin, $sop);

        $response = $this->get(route('runs.show', $run->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Runs/Show')
            ->has('run')
            ->has('teamMembers')
        );
    }

    public function test_advance_step_endpoint_advances_input_value(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = $this->createPublishedSop($admin);
        $run = app(StartSopRun::class)->execute($admin, $sop);
        $step = $run->steps->firstWhere('block_id', 'b1');

        $response = $this->postJson(route('runs.steps.advance', [$run->id, $step->id]), [
            'value' => 'Marca Acme Global',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $run->refresh();
        $this->assertEquals('Marca Acme Global', $run->inputs['marca']);
        $this->assertEquals('completed', $step->fresh()->status);
    }

    public function test_update_step_assignment_updates_assignee_and_due_date(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = $this->createPublishedSop($admin);
        $run = app(StartSopRun::class)->execute($admin, $sop);
        $step = $run->steps->first();

        $response = $this->putJson(route('runs.steps.assignment', [$run->id, $step->id]), [
            'assigned_to' => $admin->id,
            'due_at' => '2026-12-31',
            'notes' => 'Completar antes de fin de año',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $step->refresh();
        $this->assertEquals($admin->id, $step->assigned_to);
        $this->assertStringContainsString('2026-12-31', $step->due_at->toIso8601String());
        $this->assertEquals('Completar antes de fin de año', $step->notes);
    }

    public function test_destroy_deletes_run(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = $this->createPublishedSop($admin);
        $run = app(StartSopRun::class)->execute($admin, $sop);

        $response = $this->delete(route('runs.destroy', $run->id));
        $response->assertRedirect(route('runs.index'));

        $this->assertNull(SopRun::find($run->id));
    }
}
