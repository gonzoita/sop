<?php

namespace Tests\Feature;

use App\Actions\Sop\AdvanceRun;
use App\Actions\Sop\CreateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\SopRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SopRunEngineTest extends TestCase
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

    protected function createPublishedSopWithBlocks(User $user, array $blocks): \App\Models\Sop
    {
        $sop = app(CreateSop::class)->execute($user, ['title' => 'SOP de Campaña']);

        app(\App\Actions\Sop\SaveSopDraft::class)->execute($sop, [
            'schema_version' => 1,
            'blocks' => $blocks,
        ], $user);

        app(PublishSopVersion::class)->execute($sop, null, 'Versión 1', $user);

        return $sop->fresh(['currentVersion']);
    }

    public function test_start_sop_run_freezes_version_and_creates_steps(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $client = Client::create([
            'team_id' => $admin->current_team_id,
            'name' => 'Cliente Acme',
            'slug' => 'cliente-acme',
            'status' => 'active',
        ]);

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'heading',
                'props' => ['text' => 'Fase 1: Preparación'],
            ],
            [
                'id' => 'b2',
                'type' => 'input',
                'props' => ['key' => 'marca', 'label' => 'Nombre Marca', 'field' => 'text', 'required' => true],
            ],
            [
                'id' => 'b3',
                'type' => 'checklist',
                'props' => [
                    'items' => [
                        ['id' => 'i1', 'text' => 'Accesos verificados', 'required' => true],
                    ],
                ],
            ],
        ];

        $sop = $this->createPublishedSopWithBlocks($admin, $blocks);

        $run = app(StartSopRun::class)->execute($admin, $sop, [
            'client_id' => $client->id,
            'title' => 'Ejecución Acme Q3',
        ]);

        $this->assertInstanceOf(SopRun::class, $run);
        $this->assertEquals('in_progress', $run->status);
        $this->assertEquals($sop->id, $run->sop_id);
        $this->assertEquals($sop->current_version_id, $run->sop_version_id);
        $this->assertEquals($client->id, $run->client_id);
        $this->assertEquals('Ejecución Acme Q3', $run->title);

        // Heading is informational: only executable blocks (input, checklist) create steps
        $this->assertCount(2, $run->steps);
        $this->assertEquals('b2', $run->steps[0]->block_id);
        $this->assertEquals('input', $run->steps[0]->block_type);
        $this->assertEquals('pending', $run->steps[0]->status);

        $this->assertEquals('b3', $run->steps[1]->block_id);
        $this->assertEquals('checklist', $run->steps[1]->block_type);
        $this->assertEquals('pending', $run->steps[1]->status);
    }

    public function test_advance_run_captures_input_values_in_run_inputs(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => ['key' => 'marca', 'label' => 'Marca', 'field' => 'text'],
            ],
        ];

        $sop = $this->createPublishedSopWithBlocks($admin, $blocks);
        $run = app(StartSopRun::class)->execute($admin, $sop);
        $step = $run->steps->first();

        app(AdvanceRun::class)->execute($run, $step, ['value' => 'Nike Global']);

        $run->refresh();
        $this->assertEquals('Nike Global', $run->inputs['marca']);
        $this->assertEquals('completed', $run->steps->first()->status);
        $this->assertEquals(['key' => 'marca', 'value' => 'Nike Global'], $run->steps->first()->output);
        $this->assertEquals('completed', $run->status);
        $this->assertNotNull($run->completed_at);
    }

    public function test_advance_run_checklist_completes_when_all_required_checked(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'checklist',
                'props' => [
                    'items' => [
                        ['id' => 'i1', 'text' => 'Item 1 Obligatorio', 'required' => true],
                        ['id' => 'i2', 'text' => 'Item 2 Opcional', 'required' => false],
                    ],
                ],
            ],
        ];

        $sop = $this->createPublishedSopWithBlocks($admin, $blocks);
        $run = app(StartSopRun::class)->execute($admin, $sop);
        $step = $run->steps->first();

        // 1. Incomplete
        app(AdvanceRun::class)->execute($run, $step, ['checked_items' => ['i2']]);
        $this->assertEquals('pending', $step->fresh()->status);
        $this->assertEquals('in_progress', $run->fresh()->status);

        // 2. Complete with required
        app(AdvanceRun::class)->execute($run, $step, ['checked_items' => ['i1', 'i2']]);
        $this->assertEquals('completed', $step->fresh()->status);
        $this->assertEquals('completed', $run->fresh()->status);
    }

    public function test_decision_branching_marks_bypassed_blocks_as_skipped(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => ['key' => 'url', 'label' => 'Sitio Web', 'field' => 'url'],
            ],
            [
                'id' => 'b2',
                'type' => 'decision',
                'props' => [
                    'question' => '¿Tiene píxel instalado?',
                    'branches' => [
                        ['label' => 'Sí', 'goto' => 'b4'],
                        ['label' => 'No', 'goto' => 'b3'],
                    ],
                ],
            ],
            [
                'id' => 'b3',
                'type' => 'checklist',
                'props' => [
                    'items' => [
                        ['id' => 'i1', 'text' => 'Instalar Pixel', 'required' => true],
                    ],
                ],
            ],
            [
                'id' => 'b4',
                'type' => 'handoff',
                'props' => ['to' => 'client', 'message' => 'Listo para revisión'],
            ],
        ];

        $sop = $this->createPublishedSopWithBlocks($admin, $blocks);
        $run = app(StartSopRun::class)->execute($admin, $sop);

        // Complete b1
        $stepB1 = $run->steps->firstWhere('block_id', 'b1');
        app(AdvanceRun::class)->execute($run, $stepB1, ['value' => 'https://acme.com']);

        // Complete b2 choosing branch "Sí" which jumps to b4
        $stepB2 = $run->steps->firstWhere('block_id', 'b2');
        app(AdvanceRun::class)->execute($run, $stepB2, ['selected_branch' => 'Sí']);

        $run->refresh();

        $stepB3 = $run->steps->firstWhere('block_id', 'b3');
        $stepB4 = $run->steps->firstWhere('block_id', 'b4');

        // Step b3 was bypassed and must be skipped
        $this->assertEquals('skipped', $stepB3->status);

        // Step b4 was the goto target and must remain pending
        $this->assertEquals('pending', $stepB4->status);

        // Now complete b4
        app(AdvanceRun::class)->execute($run, $stepB4, []);
        $this->assertEquals('completed', $run->fresh()->status);
    }

    public function test_sop_run_policy_authorization(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $ejecutor = $this->createUserWithTeam('ejecutor');
        $ejecutor->current_team_id = $admin->current_team_id;
        $ejecutor->save();

        $cliente = $this->createUserWithTeam('cliente');

        $sop = $this->createPublishedSopWithBlocks($admin, [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => ['key' => 'test', 'label' => 'Campo Test', 'field' => 'text'],
            ],
        ]);

        $run = app(StartSopRun::class)->execute($admin, $sop);

        // Admin can view and update
        $this->assertTrue(Gate::forUser($admin)->allows('view', $run));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $run));

        // Ejecutor in same team can view and update
        $this->assertTrue(Gate::forUser($ejecutor)->allows('view', $run));
        $this->assertTrue(Gate::forUser($ejecutor)->allows('update', $run));

        // Cliente is strictly denied from internal team runs
        $this->assertFalse(Gate::forUser($cliente)->allows('view', $run));
        $this->assertFalse(Gate::forUser($cliente)->allows('update', $run));
        $this->assertFalse(Gate::forUser($cliente)->allows('viewAny', SopRun::class));
    }
}
