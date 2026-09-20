<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StepZeroFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_are_seeded_and_current_team_id_works(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['name' => 'admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'ejecutor', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'cliente', 'guard_name' => 'web']);

        $admin = User::where('email', 'admin@sopforge.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertNotNull($admin->currentTeam);
        $this->assertEquals('Agencia Principal', $admin->currentTeam->name);

        $this->actingAs($admin);
        $this->assertEquals($admin->current_team_id, currentTeamId());
    }
}