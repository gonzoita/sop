<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\Sop;
use App\Models\SopVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkillImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createEditorUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('editor');
        $user->current_team_id = $user->currentTeam->id;
        $user->save();

        return $user;
    }

    private function createSkill(User $user, string $slug = 'copy-generator'): Skill
    {
        $skill = Skill::create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Generador de Copy',
            'slug' => $slug,
            'created_by' => $user->id,
        ]);

        $version = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => "## Instrucciones del Sistema\nEres un copywriter experto.\n\n## Prompt a Ejecutar\nEscribe un titular para {{marca}} enfocado en {{beneficio}}.",
            'variables' => ['marca', 'beneficio'],
            'source' => 'manual',
            'changelog' => 'Versión inicial',
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        $skill->update(['current_version_id' => $version->id]);

        return $skill;
    }

    public function test_preview_endpoint_returns_diff_and_does_not_modify_database(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);
        $initialVersionsCount = SkillVersion::count();

        $markdownContent = <<<MD
---
tipo: skill
nombre: "Generador de Copy"
slug: "copy-generator"
version: 1
---

## Instrucciones del Sistema
Eres un copywriter y estratega de conversión experto.

## Prompt a Ejecutar
Escribe un titular persuasivo para {{marca}} destacando {{oferta}}.
MD;

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $markdownContent,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'skill_id',
            'skill_slug',
            'base_version_id',
            'diff' => [
                'total_added',
                'total_removed',
                'system_prompt',
                'user_instructions',
            ],
            'added_variables',
            'removed_variables',
            'proposed',
        ]);

        $response->assertJson([
            'added_variables' => ['oferta'],
            'removed_variables' => ['beneficio'],
        ]);

        // CRITERIO CLAVE: La base de datos no fue tocada en absoluto
        $this->assertEquals($initialVersionsCount, SkillVersion::count());
    }

    public function test_preview_endpoint_rejects_slug_mismatch_with_explicit_message(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor, 'skill-destino');

        $markdownContent = <<<MD
---
tipo: skill
slug: "skill-equivocado"
version: 1
---

## Prompt a Ejecutar
Texto nuevo para {{marca}}.
MD;

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $markdownContent,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => "El archivo corresponde al skill 'skill-equivocado', pero intentas importarlo en 'skill-destino'.",
        ]);
    }

    public function test_preview_endpoint_rejects_missing_or_malformed_frontmatter(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);

        // Sin delimitadores frontmatter
        $response1 = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => "Solo texto sin encabezado frontmatter",
        ]);
        $response1->assertStatus(422);
        $this->assertStringContainsString('front-matter', $response1->json('message'));

        // Frontmatter sin slug
        $badFrontmatter = <<<MD
---
tipo: skill
version: 1
---
Contenido sin slug
MD;
        $response2 = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $badFrontmatter,
        ]);
        $response2->assertStatus(422);
        $this->assertStringContainsString('slug', $response2->json('message'));
    }

    public function test_preview_endpoint_warns_if_file_version_is_older_than_current(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);

        // Crear una versión 2 vigente
        $v2 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 2,
            'instructions' => "V2 instrucciones",
            'variables' => ['marca'],
            'source' => 'manual',
            'changelog' => 'V2',
            'created_by' => $editor->id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $v2->id]);

        // Intentar importar un archivo basado en v1
        $markdownContent = <<<MD
---
tipo: skill
slug: "copy-generator"
version: 1
---
## Prompt a Ejecutar
Texto editado sobre version 1 para {{marca}}.
MD;

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $markdownContent,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('warning'));
        $this->assertStringContainsString('se basa en la versión v1, pero la versión vigente actual es v2', $response->json('warning'));
    }

    public function test_preview_endpoint_flags_removed_variables_used_in_published_sops(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor); // variables actuales: marca, beneficio

        // Crear un SOP publicado que usa la variable {{beneficio}}
        $sop = Sop::create([
            'team_id' => $editor->currentTeam->id,
            'title' => 'Campaña de Tráfico en Meta',
            'slug' => 'campana-trafico-meta',
            'status' => 'published',
            'created_by' => $editor->id,
        ]);

        $sopVersion = SopVersion::create([
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => [
                'schema_version' => 1,
                'blocks' => [
                    [
                        'id' => 'b1',
                        'type' => 'input',
                        'props' => ['key' => 'beneficio', 'label' => 'Beneficio principal'],
                    ],
                    [
                        'id' => 'b2',
                        'type' => 'instruction',
                        'props' => ['html' => 'El beneficio es {{beneficio}}'],
                    ],
                ],
            ],
            'created_by' => $editor->id,
            'published_at' => now(),
        ]);
        $sop->update(['current_version_id' => $sopVersion->id]);

        // Proponer un Markdown que elimina {{beneficio}} y solo deja {{marca}}
        $markdownContent = <<<MD
---
tipo: skill
slug: "copy-generator"
version: 1
---
## Prompt a Ejecutar
Texto sin la variable anterior, solo {{marca}}.
MD;

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $markdownContent,
        ]);

        $response->assertStatus(200);
        $removedInSops = $response->json('removed_variables_in_sops');
        $this->assertArrayHasKey('beneficio', $removedInSops);
        $this->assertContains('Campaña de Tráfico en Meta', $removedInSops['beneficio']);
    }

    public function test_preview_endpoint_rejects_file_exceeding_max_size(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);

        // Crear contenido mayor a 200 KB
        $largeBody = str_repeat('a', 205 * 1024);
        $largeContent = "---\nslug: copy-generator\n---\n" . $largeBody;

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => $largeContent,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('supera el tamaño máximo', $response->json('message'));
    }

    public function test_confirm_import_creates_new_version_with_import_markdown_source_and_changelog(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);
        $baseVersionId = $skill->current_version_id;

        $response = $this->postJson(route('skills.import.confirm', $skill->id), [
            'base_version_id' => $baseVersionId,
            'content' => "## Prompt a Ejecutar\nInstrucciones mejoradas en Gemini para {{marca}} y {{objetivo}}.",
            'changelog' => 'Optimizada estructura y tono persuasivo en Gemini',
        ]);

        $response->assertStatus(200);
        $skill->refresh();

        $this->assertEquals(2, $skill->currentVersion->version_number);
        $this->assertEquals('import_markdown', $skill->currentVersion->source);
        $this->assertEquals('Optimizada estructura y tono persuasivo en Gemini', $skill->currentVersion->changelog);
        $this->assertEquals(['marca', 'objetivo'], $skill->currentVersion->variables);
    }

    public function test_confirm_import_is_rejected_if_skill_changed_since_preview(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = $this->createSkill($editor);
        $oldVersionId = $skill->current_version_id;

        // Simulamos que otra persona publicó una versión mientras mirábamos el diff
        $concurrentVersion = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 2,
            'instructions' => "Versión creada por otro colega",
            'source' => 'manual',
            'changelog' => 'Paralelo',
            'created_by' => $editor->id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $concurrentVersion->id]);

        // Intentar confirmar con la versión base anterior
        $response = $this->postJson(route('skills.import.confirm', $skill->id), [
            'base_version_id' => $oldVersionId,
            'content' => "Instrucciones mías",
            'changelog' => 'Mi cambio',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Este skill cambió desde la vista previa; vuelve a revisarlo.', $response->json('message'));
    }

    public function test_team_isolation_blocks_preview_and_confirm_across_teams(): void
    {
        $teamAUser = $this->createEditorUser();
        $teamBUser = $this->createEditorUser();

        $skillTeamA = $this->createSkill($teamAUser);

        // Team B intenta hacer preview sobre Skill de Team A
        $this->actingAs($teamBUser);
        session(['current_team_id' => $teamBUser->currentTeam->id]);

        $response = $this->postJson(route('skills.import.preview', $skillTeamA->id), [
            'content' => "---\nslug: copy-generator\n---\nPrompt",
        ]);

        $response->assertStatus(404);

        // Team B intenta confirmar sobre Skill de Team A
        $confirmResponse = $this->postJson(route('skills.import.confirm', $skillTeamA->id), [
            'base_version_id' => $skillTeamA->current_version_id,
            'content' => "Prompt",
            'changelog' => 'Intrusión',
        ]);

        $confirmResponse->assertStatus(404);
    }

    public function test_roles_cliente_and_ejecutor_cannot_import_skills(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        // Usuario con rol ejecutor
        $ejecutor = User::factory()->create(['current_team_id' => $team->id]);
        $ejecutor->assignRole('ejecutor');

        $skill = Skill::create([
            'team_id' => $team->id,
            'name' => 'Skill Equipo',
            'slug' => 'skill-equipo',
            'created_by' => $team->user_id,
        ]);
        $version = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'Base',
            'created_by' => $team->user_id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $version->id]);

        $this->actingAs($ejecutor);
        session(['current_team_id' => $team->id]);

        $response = $this->postJson(route('skills.import.preview', $skill->id), [
            'content' => "---\nslug: skill-equipo\n---\nTexto",
        ]);
        $response->assertStatus(403);

        $confirm = $this->postJson(route('skills.import.confirm', $skill->id), [
            'base_version_id' => $skill->current_version_id,
            'content' => "Texto",
            'changelog' => 'Ejecutor intento',
        ]);
        $confirm->assertStatus(403);
    }
}
