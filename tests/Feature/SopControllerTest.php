<?php

namespace Tests\Feature;

use App\Actions\Sop\CreateSop;
use App\Models\Sop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SopControllerTest extends TestCase
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

    public function test_sops_index_is_accessible_by_admin(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->get(route('sops.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sops/Index'));
    }

    public function test_cliente_cannot_access_sops(): void
    {
        $cliente = $this->createUserWithTeam('cliente');
        $this->actingAs($cliente);
        session(['current_team_id' => $cliente->current_team_id]);

        $response = $this->get(route('sops.index'));
        $response->assertStatus(403);
    }

    public function test_store_creates_sop_and_redirects_to_edit(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->post(route('sops.store'), [
            'title' => 'SOP Nuevo de Ventas',
            'category' => 'Ventas',
            'description' => 'Guía de prospección',
        ]);

        $sop = Sop::where('title', 'SOP Nuevo de Ventas')->first();
        $this->assertNotNull($sop);
        $response->assertRedirect(route('sops.edit', $sop->id));
    }

    public function test_edit_loads_builder_page_with_blocks(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Marketing']);

        $response = $this->get(route('sops.edit', $sop->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sops/Edit')
            ->has('sop')
            ->has('initialBlocks')
        );
    }

    public function test_save_draft_endpoint_updates_blocks(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Draft Test']);

        $newBlocks = [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'heading',
                    'props' => ['text' => 'Paso 1: Configuración'],
                ],
            ],
        ];

        $response = $this->putJson(route('sops.draft.save', $sop->id), [
            'blocks' => $newBlocks,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'version_number' => 1]);

        $draft = $sop->fresh()->versions()->whereNull('published_at')->first();
        $this->assertEquals($newBlocks, $draft->blocks);
    }

    public function test_publish_endpoint_with_valid_blocks_succeeds(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP para Publicar']);

        $validBlocks = [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'input',
                    'props' => ['key' => 'marca', 'label' => 'Marca', 'field' => 'text'],
                ],
                [
                    'id' => 'b2',
                    'type' => 'text',
                    'props' => ['html' => '<p>Bienvenido {{marca}}</p>'],
                ],
            ],
        ];

        $response = $this->postJson(route('sops.publish', $sop->id), [
            'blocks' => $validBlocks,
            'changelog' => 'Lanzamiento inicial',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'version_number' => 1]);

        $sop->refresh();
        $this->assertEquals('published', $sop->status);
        $this->assertNotNull($sop->current_version_id);
    }

    public function test_publish_endpoint_with_invalid_blocks_returns_validation_error(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Invalido']);

        $invalidBlocks = [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'text',
                    'props' => ['html' => '<p>{{variable_inexistente}}</p>'],
                ],
            ],
        ];

        $response = $this->postJson(route('sops.publish', $sop->id), [
            'blocks' => $invalidBlocks,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($response->json('success'));
    }

    public function test_duplicate_creates_new_sop_and_redirects(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Original']);

        $response = $this->post(route('sops.duplicate', $sop->id));

        $duplicate = Sop::where('title', 'SOP Original (Copia)')->first();
        $this->assertNotNull($duplicate);
        $response->assertRedirect(route('sops.edit', $duplicate->id));
    }

    public function test_store_creates_sop_as_template(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->post(route('sops.store'), [
            'title' => 'Plantilla de Campañas',
            'is_template' => true,
        ]);

        $sop = Sop::where('title', 'Plantilla de Campañas')->first();
        $this->assertNotNull($sop);
        $this->assertTrue($sop->is_template);
        $response->assertRedirect(route('sops.edit', $sop->id));
    }

    public function test_sops_index_filters_by_template(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        app(CreateSop::class)->execute($admin, ['title' => 'SOP Normal', 'is_template' => false]);
        app(CreateSop::class)->execute($admin, ['title' => 'SOP Plantilla Especial', 'is_template' => true]);

        $response = $this->get(route('sops.index', ['status' => 'template']));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Sops/Index')
            ->where('sops.data.0.title', 'SOP Plantilla Especial')
            ->has('sops.data', 1)
        );
    }

    public function test_export_markdown_returns_attachment(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Exportable MD']);

        $response = $this->get(route('sops.export.markdown', $sop->id));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('# SOP Exportable MD', $response->getContent());
    }

    public function test_export_pdf_returns_pdf_response(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Exportable PDF']);

        $response = $this->get(route('sops.export.pdf', $sop->id));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
