<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_without_2fa_is_blocked_from_admin_panel(): void
    {
        $admin = User::where('email', 'admin@sopforge.com')->first();
        // Asegurar que no tiene 2FA
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        // Petición Web regular redirige al perfil para activar 2FA
        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('warning');

        // Petición JSON/API es bloqueada con 403
        $jsonResponse = $this->getJson(route('admin.audit-logs.index'));
        $jsonResponse->assertStatus(403);
        $jsonResponse->assertJson(['requires_two_factor' => true]);
    }

    public function test_admin_with_2fa_can_access_admin_panel(): void
    {
        $admin = User::where('email', 'admin@sopforge.com')->first();
        $admin->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin);
        session(['current_team_id' => $admin->current_team_id]);

        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
    }

    public function test_non_admin_is_not_forced_to_have_2fa_on_regular_routes(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('ejecutor');

        $this->actingAs($user);
        session(['current_team_id' => $user->current_team_id]);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_login_attempts_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'spam@test.com',
                'password' => 'wrong-password',
            ]);
        }

        // El 6to intento debe recibir 429 Too Many Requests
        $response = $this->post('/login', [
            'email' => 'spam@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_password_reset_requests_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', [
                'email' => 'test@test.com',
            ]);
        }

        // El 6to intento debe recibir 429 Too Many Requests
        $response = $this->post('/forgot-password', [
            'email' => 'test@test.com',
        ]);

        $response->assertStatus(429);
    }
}