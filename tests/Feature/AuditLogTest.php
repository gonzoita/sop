<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_client_creation_and_update_are_logged_in_audit_log(): void
    {
        $admin = User::where('email', 'admin@sopforge.com')->first();
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $client = Client::create([
            'name' => 'Cliente Demo',
            'slug' => 'cliente-demo',
            'contact_email' => 'demo@cliente.com',
            'status' => 'active',
        ]);

        $createActivity = Activity::where('subject_type', Client::class)
            ->where('subject_id', $client->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($createActivity);
        $this->assertEquals('clients', $createActivity->log_name);
        $this->assertEquals($admin->current_team_id, $createActivity->team_id);
        $this->assertEquals($admin->id, $createActivity->causer_id);

        $client->update(['name' => 'Cliente Demo Actualizado']);

        $updateActivity = Activity::where('subject_type', Client::class)
            ->where('subject_id', $client->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($updateActivity);
        $this->assertEquals('Cliente Demo Actualizado', $updateActivity->properties['attributes']['name']);
    }

    public function test_failed_login_attempt_is_logged_in_audit_log(): void
    {
        event(new Failed('web', null, ['email' => 'intruso@desconocido.com', 'password' => 'invalida']));

        $activity = Activity::withoutGlobalScope('team')
            ->where('log_name', 'auth')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('intruso@desconocido.com', $activity->properties['email']);
        $this->assertStringContainsString('Intento fallido de inicio de sesión', $activity->description);
    }

    public function test_admin_can_access_audit_log_index(): void
    {
        $admin = User::where('email', 'admin@sopforge.com')->first();
        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_audit_log_index(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('ejecutor');
        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertStatus(403);
    }

    public function test_audit_logs_have_no_modification_or_deletion_routes(): void
    {
        $admin = User::where('email', 'admin@sopforge.com')->first();
        $this->actingAs($admin);

        // No existen rutas de borrado o actualización
        $this->delete('/admin/audit-logs/1')->assertNotFound();
        $this->put('/admin/audit-logs/1', [])->assertNotFound();
        $this->post('/admin/audit-logs', [])->assertStatus(405);
    }
}