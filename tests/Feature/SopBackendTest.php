<?php

namespace Tests\Feature;

use App\Actions\Sop\CreateSop;
use App\Actions\Sop\DuplicateSop;
use App\Actions\Sop\PublishSopVersion;
use App\Actions\Sop\SaveSopDraft;
use App\Exceptions\SopValidationException;
use App\Models\Sop;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SopBackendTest extends TestCase
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

    public function test_create_sop_action_creates_sop_and_initial_draft_version(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $createSop = app(CreateSop::class);
        $sop = $createSop->execute($user, [
            'title' => 'Onboarding de Clientes',
            'description' => 'Procedimiento de alta inicial',
            'category' => 'Marketing',
        ]);

        $this->assertInstanceOf(Sop::class, $sop);
        $this->assertEquals('Onboarding de Clientes', $sop->title);
        $this->assertEquals('onboarding-de-clientes', $sop->slug);
        $this->assertEquals('draft', $sop->status);
        $this->assertNull($sop->current_version_id);
        $this->assertEquals($user->current_team_id, $sop->team_id);

        $versions = $sop->versions;
        $this->assertCount(1, $versions);
        $v1 = $versions->first();
        $this->assertEquals(1, $v1->version_number);
        $this->assertTrue($v1->isDraft());
        $this->assertFalse($v1->isPublished());
    }

    public function test_save_sop_draft_updates_existing_draft_or_creates_new_version_if_published(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $sop = app(CreateSop::class)->execute($user, [
            'title' => 'Campaña Meta Ads',
        ]);

        $newBlocks = [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'input',
                    'props' => ['key' => 'marca', 'label' => 'Marca', 'field' => 'text'],
                ],
            ],
        ];

        // 1. Updating unpublished draft keeps version 1
        $draft = app(SaveSopDraft::class)->execute($sop, $newBlocks, $user);
        $this->assertEquals(1, $draft->version_number);
        $this->assertEquals($newBlocks, $draft->fresh()->blocks);
        $this->assertCount(1, $sop->fresh()->versions);

        // 2. Publish version 1
        app(PublishSopVersion::class)->execute($sop, $draft);
        $this->assertEquals('published', $sop->fresh()->status);
        $this->assertEquals($draft->id, $sop->fresh()->current_version_id);

        // 3. Saving draft again creates version 2 because version 1 is already published
        $v2Blocks = [
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

        $draft2 = app(SaveSopDraft::class)->execute($sop, $v2Blocks, $user);
        $this->assertEquals(2, $draft2->version_number);
        $this->assertTrue($draft2->isDraft());
        $this->assertCount(2, $sop->fresh()->versions);
    }

    public function test_publish_sop_version_validates_and_rejects_invalid_references(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $sop = app(CreateSop::class)->execute($user, [
            'title' => 'SOP con Errores',
        ]);

        // Bloque con variable no definida
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

        app(SaveSopDraft::class)->execute($sop, $invalidBlocks, $user);

        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("Variable no definida: la variable '{{variable_inexistente}}'");

        app(PublishSopVersion::class)->execute($sop);
    }

    public function test_publish_sop_version_succeeds_with_valid_blocks(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $sop = app(CreateSop::class)->execute($user, [
            'title' => 'SOP Válido',
        ]);

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
                    'props' => ['html' => '<p>Marca: {{marca}}</p>'],
                ],
            ],
        ];

        app(SaveSopDraft::class)->execute($sop, $validBlocks, $user);

        $publishedVersion = app(PublishSopVersion::class)->execute($sop, null, 'Publicación inicial');

        $this->assertTrue($publishedVersion->isPublished());
        $this->assertEquals('published', $sop->fresh()->status);
        $this->assertEquals($publishedVersion->id, $sop->fresh()->current_version_id);
    }

    public function test_duplicate_sop_creates_independent_copy(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $sop = app(CreateSop::class)->execute($user, [
            'title' => 'Plantilla Base',
            'category' => 'Operaciones',
        ]);

        $copy = app(DuplicateSop::class)->execute($sop, $user);

        $this->assertInstanceOf(Sop::class, $copy);
        $this->assertNotEquals($sop->id, $copy->id);
        $this->assertEquals('Plantilla Base (Copia)', $copy->title);
        $this->assertEquals('plantilla-base-copia', $copy->slug);
        $this->assertEquals('Operaciones', $copy->category);
        $this->assertEquals('draft', $copy->status);
    }

    public function test_team_isolation_on_sops(): void
    {
        $userA = $this->createUserWithTeam('admin');
        $teamA = $userA->currentTeam;

        $userB = $this->createUserWithTeam('admin');
        $teamB = $userB->currentTeam;

        // User A creates SOP
        $this->actingAs($userA);
        session(['current_team_id' => $teamA->id]);
        $sopA = app(CreateSop::class)->execute($userA, ['title' => 'SOP Equipo A']);

        // User B creates SOP
        $this->actingAs($userB);
        session(['current_team_id' => $teamB->id]);
        $sopB = app(CreateSop::class)->execute($userB, ['title' => 'SOP Equipo B']);

        // User A connects and should only see SOP A
        $this->actingAs($userA);
        session(['current_team_id' => $teamA->id]);

        $sops = Sop::all();
        $this->assertCount(1, $sops);
        $this->assertTrue($sops->contains($sopA));
        $this->assertFalse($sops->contains($sopB));
        $this->assertNull(Sop::find($sopB->id));
    }

    public function test_sop_policy_forbids_cliente_and_enforces_permissions(): void
    {
        $admin = $this->createUserWithTeam('admin');
        $team = $admin->currentTeam;

        $editor = User::factory()->create(['current_team_id' => $team->id]);
        $editor->assignRole('editor');

        $ejecutor = User::factory()->create(['current_team_id' => $team->id]);
        $ejecutor->assignRole('ejecutor');

        $cliente = User::factory()->create(['current_team_id' => $team->id]);
        $cliente->assignRole('cliente');

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);
        $sop = app(CreateSop::class)->execute($admin, ['title' => 'SOP Auditoría']);

        // Cliente está completamente bloqueado
        $this->assertFalse(Gate::forUser($cliente)->allows('viewAny', Sop::class));
        $this->assertFalse(Gate::forUser($cliente)->allows('view', $sop));
        $this->assertFalse(Gate::forUser($cliente)->allows('create', Sop::class));
        $this->assertFalse(Gate::forUser($cliente)->allows('update', $sop));
        $this->assertFalse(Gate::forUser($cliente)->allows('publish', $sop));
        $this->assertFalse(Gate::forUser($cliente)->allows('duplicate', $sop));
        $this->assertFalse(Gate::forUser($cliente)->allows('delete', $sop));

        // Ejecutor solo puede ver
        $this->assertTrue(Gate::forUser($ejecutor)->allows('viewAny', Sop::class));
        $this->assertTrue(Gate::forUser($ejecutor)->allows('view', $sop));
        $this->assertFalse(Gate::forUser($ejecutor)->allows('create', Sop::class));
        $this->assertFalse(Gate::forUser($ejecutor)->allows('update', $sop));
        $this->assertFalse(Gate::forUser($ejecutor)->allows('publish', $sop));

        // Editor y Admin pueden crear, editar, publicar
        $this->assertTrue(Gate::forUser($editor)->allows('view', $sop));
        $this->assertTrue(Gate::forUser($editor)->allows('create', Sop::class));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $sop));
        $this->assertTrue(Gate::forUser($editor)->allows('publish', $sop));
        $this->assertTrue(Gate::forUser($editor)->allows('duplicate', $sop));

        $this->assertTrue(Gate::forUser($admin)->allows('create', Sop::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $sop));
        $this->assertTrue(Gate::forUser($admin)->allows('publish', $sop));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $sop));
    }
}
