<?php

namespace Tests\Feature;

use App\Actions\Sop\StartSopRun;
use App\Jobs\RunAiTask;
use App\Models\Activity;
use App\Models\AiBudget;
use App\Models\AiCredential;
use App\Models\AiGeneration;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Models\SopVersion;
use App\Models\User;
use App\Services\OpenRouter\Contracts\AiProvider;
use App\Services\OpenRouter\FakeAiProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunAiTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(AiProvider::class, new FakeAiProvider());
    }

    private function createSetupData(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->two_factor_secret = encrypt('secret');
        $admin->two_factor_confirmed_at = now();
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();

        $team = $admin->currentTeam;

        // Credencial activa
        $credential = AiCredential::create([
            'team_id' => $team->id,
            'provider' => 'openrouter',
            'label' => 'OpenRouter Test',
            'api_key' => 'sk-or-v1-supersecretkey12345678',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // Presupuesto
        $budget = AiBudget::create([
            'team_id' => $team->id,
            'monthly_limit_usd' => 50.00,
            'alert_at_percent' => 80,
        ]);

        // Skill
        $skill = Skill::create([
            'team_id' => $team->id,
            'name' => 'Generador Brief',
            'slug' => 'generador-brief',
            'description' => 'Crea brief',
            'created_by' => $admin->id,
        ]);

        $skillVersion = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'Eres un estratega. Brief para {{nombre_marca}}.',
            'variables' => ['nombre_marca'],
            'source' => 'manual',
            'changelog' => 'Inicial',
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $skillVersion->id]);

        // SOP con bloque ai_task
        $sop = Sop::create([
            'team_id' => $team->id,
            'title' => 'SOP con IA Test',
            'slug' => 'sop-con-ia-test',
            'description' => 'SOP para pruebas de IA',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);

        $sopVersion = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => [
                'schema_version' => 1,
                'blocks' => [
                    [
                        'id' => 'bloque-input',
                        'type' => 'input',
                        'props' => [
                            'key' => 'nombre_marca',
                            'label' => 'Nombre de la Marca',
                            'field_type' => 'text',
                        ],
                    ],
                    [
                        'id' => 'bloque-ai',
                        'type' => 'ai_task',
                        'props' => [
                            'skill_slug' => 'generador-brief',
                            'model' => 'openai/gpt-4o-mini',
                            'prompt' => 'Genera propuesta para {{nombre_marca}}',
                            'output_key' => 'brief_resultado',
                            'requires_approval' => true,
                        ],
                    ],
                ],
            ],
            'published_at' => now(),
        ]);
        $sop->update(['current_version_id' => $sopVersion->id]);

        return compact('admin', 'team', 'credential', 'budget', 'skill', 'sop', 'sopVersion');
    }

    public function test_ai_task_step_runs_in_background_and_enters_awaiting_approval(): void
    {
        $data = $this->createSetupData();

        $fakeProvider = new FakeAiProvider(
            defaultContent: 'Este es el brief generado por la IA para Marca Alpha.',
            defaultInputTokens: 90,
            defaultOutputTokens: 210,
            defaultCostUsd: 0.0012
        );
        $this->app->instance(AiProvider::class, $fakeProvider);

        // Iniciar corrida con input ya completado
        $startAction = new StartSopRun();
        $run = $startAction->execute($data['admin'], $data['sop'], [
            'inputs' => ['nombre_marca' => 'Marca Alpha'],
        ]);

        $aiStep = $run->steps()->where('block_type', 'ai_task')->first();
        $this->assertNotNull($aiStep);

        $fakeProvider->assertCalled(1);

        $aiStep->refresh();
        $run->refresh();

        // 1. Debe estar en awaiting_approval
        $this->assertEquals('awaiting_approval', $aiStep->status);
        $this->assertEquals('awaiting_approval', $run->status);

        // 2. El output está guardado en el paso
        $this->assertNotNull($aiStep->output);
        $this->assertEquals('Este es el brief generado por la IA para Marca Alpha.', $aiStep->output['content']);
        $this->assertEquals('brief_resultado', $aiStep->output['output_key']);

        // 3. REGLA CRÍTICA: NO debe haberse propagado a run->outputs todavía
        $this->assertArrayNotHasKey('brief_resultado', $run->outputs ?? []);

        // 4. Registro en ai_generations
        $generation = AiGeneration::where('sop_run_step_id', $aiStep->id)->first();
        $this->assertNotNull($generation);
        $this->assertEquals('succeeded', $generation->status);
        $this->assertEquals(90, $generation->input_tokens);
        $this->assertEquals(210, $generation->output_tokens);
        $this->assertEquals(0.0012, (float) $generation->cost_usd);
        $this->assertStringContainsString('Marca Alpha', $generation->prompt);
    }

    public function test_human_approval_unlocks_output_and_updates_run(): void
    {
        $data = $this->createSetupData();

        $startAction = new StartSopRun();
        $run = $startAction->execute($data['admin'], $data['sop'], [
            'inputs' => ['nombre_marca' => 'Marca Beta'],
        ]);

        $aiStep = $run->steps()->where('block_type', 'ai_task')->first();
        $aiStep->update([
            'status' => 'awaiting_approval',
            'output' => [
                'content' => 'Borrador preliminar de brief',
                'output_key' => 'brief_resultado',
                'model' => 'openai/gpt-4o-mini',
            ],
        ]);
        $run->update(['status' => 'awaiting_approval']);

        $this->actingAs($data['admin']);
        session(['current_team_id' => $data['team']->id]);

        // Aprobar con edición humana
        $response = $this->post(route('runs.steps.approve-ai', [
            'run' => $run->id,
            'step' => $aiStep->id,
        ]), [
            'edited_content' => 'Borrador editado y perfeccionado por el equipo.',
        ]);

        $response->assertRedirect();
        $aiStep->refresh();
        $run->refresh();

        // Estado del paso aprobado
        $this->assertEquals('approved', $aiStep->status);
        $this->assertNotNull($aiStep->completed_at);
        $this->assertEquals('Borrador editado y perfeccionado por el equipo.', $aiStep->output['content']);

        // El output ahora SÍ está disponible para los siguientes bloques
        $this->assertEquals('Borrador editado y perfeccionado por el equipo.', $run->outputs['brief_resultado']);

        // Auditoría registrada
        $activity = Activity::where('log_name', 'ai_approval')
            ->where('subject_id', $aiStep->id)
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('aprobado', $activity->description);
    }

    public function test_human_rejection_marks_step_rejected_and_does_not_unlock_output(): void
    {
        $data = $this->createSetupData();

        $startAction = new StartSopRun();
        $run = $startAction->execute($data['admin'], $data['sop'], [
            'inputs' => ['nombre_marca' => 'Marca Gamma'],
        ]);

        $aiStep = $run->steps()->where('block_type', 'ai_task')->first();
        $aiStep->update([
            'status' => 'awaiting_approval',
            'output' => [
                'content' => 'Texto que no cumple requerimientos',
                'output_key' => 'brief_resultado',
            ],
        ]);

        $this->actingAs($data['admin']);
        session(['current_team_id' => $data['team']->id]);

        $response = $this->post(route('runs.steps.reject-ai', [
            'run' => $run->id,
            'step' => $aiStep->id,
        ]), [
            'reason' => 'No respetó el tono de voz de la marca.',
        ]);

        $response->assertRedirect();
        $aiStep->refresh();
        $run->refresh();

        $this->assertEquals('rejected', $aiStep->status);
        $this->assertEquals('No respetó el tono de voz de la marca.', $aiStep->notes);

        // No debe estar en run->outputs
        $this->assertArrayNotHasKey('brief_resultado', $run->outputs ?? []);
    }

    public function test_ai_task_is_halted_when_budget_is_exceeded(): void
    {
        $data = $this->createSetupData();

        // Configurar presupuesto de 10 USD
        $data['budget']->update(['monthly_limit_usd' => 10.00]);

        // Simular gasto previo de 10.50 USD en el mes
        AiGeneration::create([
            'team_id' => $data['team']->id,
            'model' => 'test-model',
            'prompt' => 'Gasto previo',
            'cost_usd' => 10.50,
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $fakeProvider = new FakeAiProvider();
        $this->app->instance(AiProvider::class, $fakeProvider);

        $startAction = new StartSopRun();
        $run = $startAction->execute($data['admin'], $data['sop'], [
            'inputs' => ['nombre_marca' => 'Marca Delta'],
        ]);

        $aiStep = $run->steps()->where('block_type', 'ai_task')->first();

        // Ejecutar Job
        $job = new RunAiTask($aiStep->id);
        $job->handle($fakeProvider);

        // El proveedor NO debe haber sido llamado
        $this->assertCount(0, $fakeProvider->calls);

        $aiStep->refresh();
        $this->assertEquals('pending', $aiStep->status);
        $this->assertStringContainsString('Límite mensual de presupuesto de IA alcanzado', $aiStep->notes);
    }
}
