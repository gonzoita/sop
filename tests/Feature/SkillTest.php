<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTest extends TestCase
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

    public function test_user_with_permission_can_view_skills_index(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = Skill::create([
            'team_id' => $editor->currentTeam->id,
            'name' => 'Generador de Brief',
            'slug' => 'generador-de-brief',
            'description' => 'Crea briefs para marcas.',
            'created_by' => $editor->id,
        ]);

        $version = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'Instrucciones para {{marca}}',
            'variables' => ['marca'],
            'source' => 'manual',
            'changelog' => 'Inicial',
            'created_by' => $editor->id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $version->id]);

        $response = $this->get(route('skills.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Skills/Index')
            ->has('skills', 1)
            ->where('skills.0.name', 'Generador de Brief')
            ->where('skills.0.current_variables.0', 'marca')
        );
    }

    public function test_user_can_create_skill_with_initial_version_and_auto_extracted_variables(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $response = $this->post(route('skills.store'), [
            'name' => 'Estructura de Campaña',
            'slug' => 'estructura-de-campana',
            'description' => 'Diseña la arquitectura de campaña publicitaria.',
            'instructions' => 'Eres un media buyer experto. Genera campaña para {{cliente}} con objetivo {{objetivo}} y presupuesto {{presupuesto}}.',
        ]);

        $skill = Skill::where('slug', 'estructura-de-campana')->first();
        $this->assertNotNull($skill);
        $response->assertRedirect(route('skills.show', $skill->id));

        $this->assertNotNull($skill->current_version_id);
        $version = $skill->currentVersion;
        $this->assertEquals(1, $version->version_number);
        $this->assertEquals(['cliente', 'objetivo', 'presupuesto'], $version->variables);
        $this->assertEquals('manual', $version->source);
    }

    public function test_user_can_publish_new_version_with_changelog(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = Skill::create([
            'team_id' => $editor->currentTeam->id,
            'name' => 'Copywriting Meta Ads',
            'slug' => 'copywriting-meta-ads',
            'created_by' => $editor->id,
        ]);

        $v1 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'Escribe copys para {{producto}}.',
            'variables' => ['producto'],
            'changelog' => 'V1',
            'created_by' => $editor->id,
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $v1->id]);

        $response = $this->post(route('skills.publish-version', $skill->id), [
            'instructions' => 'Escribe copys persuasivos para {{producto}} con fórmula AIDA dirigida a {{audiencia}}.',
            'changelog' => 'Añadida fórmula AIDA y variable audiencia.',
        ]);

        $response->assertRedirect();
        $skill->refresh();

        $v2 = $skill->currentVersion;
        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals(['producto', 'audiencia'], $v2->variables);
        $this->assertEquals('Añadida fórmula AIDA y variable audiencia.', $v2->changelog);
        $this->assertCount(2, $skill->versions);
    }

    public function test_user_can_set_current_version(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = Skill::create([
            'team_id' => $editor->currentTeam->id,
            'name' => 'Skill Test',
            'slug' => 'skill-test',
            'created_by' => $editor->id,
        ]);

        $v1 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'V1',
            'created_at' => now(),
        ]);
        $v2 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 2,
            'instructions' => 'V2',
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $v2->id]);

        // Rollback to V1
        $this->post(route('skills.set-current', ['skill' => $skill->id, 'version' => $v1->id]));

        $this->assertEquals($v1->id, $skill->fresh()->current_version_id);
    }

    public function test_user_can_export_and_import_skill_markdown(): void
    {
        $editor = $this->createEditorUser();
        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        $skill = Skill::create([
            'team_id' => $editor->currentTeam->id,
            'name' => 'Briefing IA',
            'slug' => 'briefing-ia',
            'created_by' => $editor->id,
        ]);

        $v1 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version_number' => 1,
            'instructions' => 'Instrucciones iniciales para {{marca}}.',
            'variables' => ['marca'],
            'changelog' => 'V1',
            'created_at' => now(),
        ]);
        $skill->update(['current_version_id' => $v1->id]);

        // Export
        $exportResponse = $this->get(route('skills.export.markdown', $skill->id));
        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader('Content-Disposition');

        // Import (Vía B)
        $externalMarkdown = <<<MD
---
tipo: skill
nombre: "Briefing IA"
slug: "briefing-ia"
version: 1
---

## Instrucciones

Instrucciones perfeccionadas en Claude para {{marca}} y {{presupuesto}}.
MD;

        $importResponse = $this->post(route('skills.import.markdown', $skill->id), [
            'content' => $externalMarkdown,
            'changelog' => 'Afinado externamente en Claude',
        ]);

        $importResponse->assertRedirect();
        $skill->refresh();

        $v2 = $skill->currentVersion;
        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals('import_markdown', $v2->source);
        $this->assertEquals(['marca', 'presupuesto'], $v2->variables);
        $this->assertStringContainsString('Instrucciones perfeccionadas en Claude', $v2->instructions);
    }
}
