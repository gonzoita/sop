<?php

namespace Tests\Feature;

use App\Actions\Sop\CreateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\SaveSopDraft;
use App\Actions\Sop\StartSopRun;
use App\Models\Client;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Models\User;
use App\Services\Runs\RunDocumentBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RunDeliverableExportTest extends TestCase
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

    protected function createCompleteSop(User $user): Sop
    {
        $sop = app(CreateSop::class)->execute($user, [
            'title' => 'Estrategia Digital Anual',
        ]);

        app(SaveSopDraft::class)->execute($sop, [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'inp1',
                    'type' => 'input',
                    'props' => ['key' => 'marca', 'label' => 'Nombre de la Marca'],
                ],
                [
                    'id' => 'inp2',
                    'type' => 'input',
                    'props' => ['key' => 'presupuesto', 'label' => 'Presupuesto Asignado'],
                ],
                [
                    'id' => 'inp3',
                    'type' => 'input',
                    'props' => ['key' => 'objetivo_sin_responder', 'label' => 'Objetivo Secundario'],
                ],
                [
                    'id' => 'h1',
                    'type' => 'heading',
                    'props' => ['level' => 1, 'text' => 'Estrategia para {{marca}}'],
                ],
                [
                    'id' => 'inst1',
                    'type' => 'text',
                    'props' => [
                        'html' => '<p>Bienvenido al plan de <strong>{{marca}}</strong>. Presupuesto: <em>{{presupuesto}}</em>.</p><ul><li>Punto 1</li></ul>',
                    ],
                ],
                [
                    'id' => 'chk1',
                    'type' => 'checklist',
                    'props' => [
                        'title' => 'Lista de Verificación de {{marca}}',
                        'items' => [
                            ['id' => 'chk_a', 'text' => 'Revisión de accesos', 'required' => true],
                            ['id' => 'chk_b', 'text' => 'Análisis de competencia', 'required' => false],
                        ],
                    ],
                ],
                [
                    'id' => 'dec1',
                    'type' => 'decision',
                    'props' => [
                        'question' => 'Tipo de Pauta',
                        'label' => 'Tipo de Pauta',
                        'branches' => [
                            ['label' => 'Tráfico Web', 'goto' => 'step_goto'],
                            ['label' => 'Generación de Leads', 'goto' => null],
                        ],
                    ],
                ],
                [
                    'id' => 'bypassed_text',
                    'type' => 'text',
                    'props' => [
                        'html' => '<p>Este texto se omite si se elige Tráfico Web porque va directo a step_goto.</p>',
                    ],
                ],
                [
                    'id' => 'step_goto',
                    'type' => 'heading',
                    'props' => ['level' => 2, 'text' => 'Conclusión del Plan'],
                ],
                [
                    'id' => 'ai1',
                    'type' => 'ai_task',
                    'props' => [
                        'label' => 'Copys Persuasivos IA',
                        'skill_slug' => 'copys-ia',
                        'output_key' => 'copys_generados',
                        'model' => 'openai/gpt-4o-mini',
                    ],
                ],
                [
                    'id' => 'appr1',
                    'type' => 'approval',
                    'props' => ['label' => 'Aprobación del Director Creativo', 'role' => 'admin'],
                ],
                [
                    'id' => 'hand1',
                    'type' => 'handoff',
                    'props' => ['label' => 'Entrega al Cliente', 'to' => 'client'],
                ],
            ],
        ], $user);

        app(PublishSopVersion::class)->execute($sop, null, 'Versión inicial completa', $user);

        return $sop->fresh();
    }

    public function test_user_can_export_completed_run_with_variables_resolved(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $client = Client::create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Nike Global',
            'slug' => 'nike-global',
            'created_by' => $user->id,
        ]);

        $sop = $this->createCompleteSop($user);

        $run = app(StartSopRun::class)->execute($user, $sop, [
            'client_id' => $client->id,
            'title' => 'Estrategia Nike Q4',
            'inputs' => [
                'marca' => 'Nike Inc',
                'presupuesto' => '$50,000 USD',
            ],
        ]);

        // Simular avance de pasos
        // 1. Checklist
        $chkStep = $run->steps()->where('block_id', 'chk1')->first();
        $chkStep->update([
            'status' => 'completed',
            'output' => ['checked_items' => ['chk_a']],
        ]);

        // 2. Decisión (Elige Tráfico Web y salta bypassed_text hasta step_goto)
        $decStep = $run->steps()->where('block_id', 'dec1')->first();
        $decStep->update([
            'status' => 'completed',
            'output' => [
                'selected_branch' => 'Tráfico Web',
                'goto' => 'step_goto',
            ],
        ]);

        // 3. AI Task (Aprobada)
        $aiStep = $run->steps()->where('block_id', 'ai1')->first();
        $aiStep->update([
            'status' => 'approved',
            'output' => [
                'content' => 'Just Do It - Edición Revisada para {{marca}}',
                'output_key' => 'copys_generados',
                'approved_by' => $user->id,
            ],
        ]);

        // 4. Approval y Handoff
        $apprStep = $run->steps()->where('block_id', 'appr1')->first();
        $apprStep->update([
            'status' => 'approved',
            'completed_at' => now(),
        ]);

        $handStep = $run->steps()->where('block_id', 'hand1')->first();
        $handStep->update([
            'status' => 'completed',
            'completed_at' => now(),
            'output' => ['recipient' => 'cliente'],
        ]);

        $run->update(['status' => 'completed']);

        // Petición de descarga
        $response = $this->get(route('runs.export.deliverable', $run->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        $content = $response->streamedContent();

        // 1. Front-matter presente y estructurado
        $this->assertStringContainsString('tipo: entregable', $content);
        $this->assertStringContainsString('sop: "Estrategia Digital Anual"', $content);
        $this->assertStringContainsString('cliente: "Nike Global"', $content);
        $this->assertStringContainsString("ejecucion_id: {$run->id}", $content);
        $this->assertStringContainsString('estado: "completed"', $content);

        // 2. Variables reemplazadas
        $this->assertStringContainsString('# Estrategia para Nike Inc', $content);
        $this->assertStringContainsString('Bienvenido al plan de **Nike Inc**.', $content);
        $this->assertStringContainsString('Presupuesto: *$50,000 USD*.', $content);

        // 3. Inputs con pregunta y respuesta
        $this->assertStringContainsString('**Nombre de la Marca**', $content);
        $this->assertStringContainsString('Nike Inc', $content);

        // 4. Checklist con [x] y [ ]
        $this->assertStringContainsString('- [x] Revisión de accesos', $content);
        $this->assertStringContainsString('- [ ] Análisis de competencia', $content);

        // 5. Decisión: solo la opción elegida
        $this->assertStringContainsString('Opción elegida: Tráfico Web', $content);

        // 6. Tarea de IA aprobada
        $this->assertStringContainsString('Just Do It - Edición Revisada para Nike Inc', $content);

        // 7. Sección Registro para approval y handoff
        $this->assertStringContainsString('## Registro', $content);
        $this->assertStringContainsString('Aprobación (Aprobación del Director Creativo)', $content);
        $this->assertStringContainsString('Traspaso (Entrega al Cliente)', $content);
    }

    public function test_unanswered_variables_render_sin_respuesta_placeholder(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);

        // Corrida sin inputs
        $run = app(StartSopRun::class)->execute($user, $sop, [
            'title' => 'Corrida Incompleta',
            'inputs' => [],
        ]);

        $builder = app(RunDocumentBuilder::class);
        $content = $builder->build($run);

        // Debe renderizar [sin respuesta: clave] y no dejar {{clave}} suelto ni texto vacío
        $this->assertStringContainsString('[sin respuesta: marca]', $content);
        $this->assertStringContainsString('[sin respuesta: presupuesto]', $content);
        $this->assertStringContainsString('[sin respuesta: objetivo_sin_responder]', $content);
        $this->assertStringNotContainsString('{{marca}}', $content);
        $this->assertStringNotContainsString('{{presupuesto}}', $content);
    }

    public function test_unapproved_ai_task_renders_pendiente_de_aprobacion_never_its_content(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);

        $run = app(StartSopRun::class)->execute($user, $sop, [
            'title' => 'Corrida con IA Pendiente',
            'inputs' => ['marca' => 'Adidas'],
        ]);

        $aiStep = $run->steps()->where('block_id', 'ai1')->first();
        $secretAiDraft = 'Este texto confidencial NO aprobado nunca debe salir al público.';
        $aiStep->update([
            'status' => 'awaiting_approval',
            'output' => [
                'content' => $secretAiDraft,
                'output_key' => 'copys_generados',
            ],
        ]);

        $builder = app(RunDocumentBuilder::class);
        $content = $builder->build($run);

        // Criterio de aceptación 2:
        $this->assertStringContainsString('> Pendiente de aprobación', $content);
        $this->assertStringNotContainsString($secretAiDraft, $content);
    }

    public function test_approved_ai_task_renders_edited_content(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);

        $run = app(StartSopRun::class)->execute($user, $sop, [
            'title' => 'Corrida con IA Editada',
            'inputs' => ['marca' => 'Puma'],
        ]);

        $aiStep = $run->steps()->where('block_id', 'ai1')->first();
        $originalRawAiText = 'Texto crudo generado por IA con errores ortograficos.';
        $editedReviewerText = 'Texto pulido y perfeccionado por el revisor humano.';

        $aiStep->update([
            'status' => 'approved',
            'output' => [
                'content' => $editedReviewerText,
                'original_content' => $originalRawAiText,
                'output_key' => 'copys_generados',
            ],
        ]);

        $run->refresh();
        $builder = app(RunDocumentBuilder::class);
        $content = $builder->build($run);

        // Criterio de aceptación 3:
        $this->assertStringContainsString($editedReviewerText, $content);
        $this->assertStringNotContainsString($originalRawAiText, $content);
    }

    public function test_skipped_steps_and_unselected_decision_branches_do_not_appear(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);

        $run = app(StartSopRun::class)->execute($user, $sop, [
            'title' => 'Corrida Branching',
            'inputs' => ['marca' => 'Tesla'],
        ]);

        // Decisión elige Tráfico Web que salta bypassed_text
        $decStep = $run->steps()->where('block_id', 'dec1')->first();
        $decStep->update([
            'status' => 'completed',
            'output' => [
                'selected_branch' => 'Tráfico Web',
                'goto' => 'step_goto',
            ],
        ]);

        $aiStep = $run->steps()->where('block_id', 'ai1')->first();
        $aiStep->update(['status' => 'skipped']);

        $run->refresh();
        $builder = app(RunDocumentBuilder::class);
        $content = $builder->build($run);

        // Criterio de aceptación 4:
        $this->assertStringNotContainsString('Generación de Leads', $content);
        $this->assertStringNotContainsString('Este texto se omite si se elige Tráfico Web', $content);
        $this->assertStringNotContainsString('Copys Persuasivos IA', $content);
    }

    public function test_document_contains_no_internal_cost_tokens_model_or_prompts(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);

        $run = app(StartSopRun::class)->execute($user, $sop, [
            'title' => 'Corrida Audit Tokens',
            'inputs' => ['marca' => 'Apple'],
        ]);

        $aiStep = $run->steps()->where('block_id', 'ai1')->first();
        $aiStep->update([
            'status' => 'approved',
            'output' => [
                'content' => 'Think Different para Apple.',
                'model' => 'openai/gpt-4o-mini',
                'tokens' => 1520,
                'cost_usd' => 0.0035,
            ],
        ]);

        $run->refresh();
        $builder = app(RunDocumentBuilder::class);
        $content = $builder->build($run);

        // Criterio de aceptación 5:
        $this->assertStringNotContainsString('0.0035', $content);
        $this->assertStringNotContainsString('cost_usd', $content);
        $this->assertStringNotContainsString('1520', $content);
        $this->assertStringNotContainsString('tokens', $content);
        $this->assertStringNotContainsString('openai/gpt-4o-mini', $content);
        $this->assertStringNotContainsString('prompt', $content);
    }

    public function test_user_from_another_team_cannot_export_run(): void
    {
        $userA = $this->createUserWithTeam('admin');
        $userB = $this->createUserWithTeam('admin');

        $this->actingAs($userA);
        session(['current_team_id' => $userA->currentTeam->id]);

        $sop = $this->createCompleteSop($userA);
        $run = app(StartSopRun::class)->execute($userA, $sop, ['title' => 'Corrida Equipo A']);

        // Intentar acceder con usuario del Equipo B
        $this->actingAs($userB);
        session(['current_team_id' => $userB->currentTeam->id]);

        $response = $this->get(route('runs.export.deliverable', $run->id));

        // Criterio de aceptación 6: 403 o 404 por aislamiento multi-tenant
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_client_role_cannot_export_run(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->currentTeam->id]);

        $sop = $this->createCompleteSop($admin);
        $run = app(StartSopRun::class)->execute($admin, $sop, ['title' => 'Corrida Equipo']);

        $clienteUser = User::factory()->create();
        $clienteUser->assignRole('cliente');

        $this->actingAs($clienteUser);

        $response = $this->get(route('runs.export.deliverable', $run->id));

        // Criterio de aceptación 6: El rol cliente no accede al exportador del equipo
        $response->assertStatus(403);
    }

    public function test_export_is_recorded_in_activity_log(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->currentTeam->id]);

        $sop = $this->createCompleteSop($user);
        $run = app(StartSopRun::class)->execute($user, $sop, ['title' => 'Corrida Para Auditoría']);

        $response = $this->get(route('runs.export.deliverable', $run->id));
        $response->assertStatus(200);

        $activity = Activity::where('log_name', 'sop_runs')
            ->where('subject_type', SopRun::class)
            ->where('subject_id', $run->id)
            ->where('description', 'Entregable Markdown exportado')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($user->id, $activity->causer_id);
    }
}
