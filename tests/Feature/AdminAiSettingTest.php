<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AiBudget;
use App\Models\AiCredential;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAiSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->two_factor_secret = encrypt('secret');
        $admin->two_factor_confirmed_at = now();
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();

        return $admin;
    }

    public function test_admin_can_view_ai_settings_page(): void
    {
        $admin = $this->createAdminUser();
        $team = $admin->currentTeam;

        AiCredential::create([
            'team_id' => $team->id,
            'provider' => 'openrouter',
            'label' => 'Credencial Test',
            'api_key' => 'sk-or-v1-abcdef1234567890',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        $response = $this->get(route('admin.ai-settings.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Ai/Index')
            ->has('credentials', 1)
            ->has('budget')
            ->where('budget.monthly_limit_usd', 50)
            ->where('credentials.0.masked_api_key', '••••••••7890')
        );
    }

    public function test_admin_can_store_ai_credential_encrypted(): void
    {
        $admin = $this->createAdminUser();
        $team = $admin->currentTeam;

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        $rawKey = 'sk-or-v1-mysecretkey123456789xyz';

        $response = $this->post(route('admin.ai-settings.credentials.store'), [
            'provider' => 'openrouter',
            'label' => 'OpenRouter Producción',
            'api_key' => $rawKey,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('banner');

        $cred = AiCredential::where('label', 'OpenRouter Producción')->first();
        $this->assertNotNull($cred);
        $this->assertEquals($admin->id, $cred->created_by);
        $this->assertEquals($team->id, $cred->team_id);
        $this->assertEquals($rawKey, $cred->api_key); // Eloquent decrypts on access
        $this->assertEquals('••••••••9xyz', $cred->masked_api_key);

        // Verificar a nivel de base de datos directa que NO está en texto plano
        $rawDbRecord = DB::table('ai_credentials')->where('id', $cred->id)->first();
        $this->assertNotEquals($rawKey, $rawDbRecord->api_key);
        $this->assertStringNotContainsString($rawKey, $rawDbRecord->api_key);
    }

    public function test_api_key_is_never_leaked_in_json_or_logs(): void
    {
        $admin = $this->createAdminUser();
        $team = $admin->currentTeam;

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        $rawKey = 'sk-or-v1-supersecretkey9999';

        $cred = AiCredential::create([
            'team_id' => $team->id,
            'provider' => 'openrouter',
            'label' => 'Credencial Secreta',
            'api_key' => $rawKey,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // 1. Verificar serialización JSON del modelo
        $json = $cred->toJson();
        $this->assertStringNotContainsString($rawKey, $json);
        $this->assertEquals('••••••••9999', json_decode($json, true)['masked_api_key']);

        // 2. Verificar activity_log
        $activity = Activity::where('log_name', 'ai_credentials')
            ->where('subject_id', $cred->id)
            ->first();

        $this->assertNotNull($activity);
        $activityString = json_encode($activity->properties);
        $this->assertStringNotContainsString($rawKey, $activityString);
    }

    public function test_admin_can_toggle_and_delete_credential(): void
    {
        $admin = $this->createAdminUser();
        $team = $admin->currentTeam;

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        $cred = AiCredential::create([
            'team_id' => $team->id,
            'provider' => 'openrouter',
            'label' => 'Credencial Toggle',
            'api_key' => 'sk-or-v1-togglekey12345678',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // Toggle to inactive
        $patchResponse = $this->patch(route('admin.ai-settings.credentials.toggle', ['credential' => $cred->id]));
        $patchResponse->assertRedirect();
        $this->assertFalse($cred->fresh()->is_active);

        // Toggle back to active
        $this->patch(route('admin.ai-settings.credentials.toggle', ['credential' => $cred->id]));
        $this->assertTrue($cred->fresh()->is_active);

        // Delete
        $deleteResponse = $this->delete(route('admin.ai-settings.credentials.destroy', ['credential' => $cred->id]));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('ai_credentials', ['id' => $cred->id]);
    }

    public function test_admin_can_update_monthly_budget(): void
    {
        $admin = $this->createAdminUser();
        $team = $admin->currentTeam;

        $this->actingAs($admin);
        session(['current_team_id' => $team->id]);

        $response = $this->put(route('admin.ai-settings.budget.update'), [
            'monthly_limit_usd' => 150.50,
            'alert_at_percent' => 75,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('banner');

        $budget = AiBudget::where('team_id', $team->id)->first();
        $this->assertNotNull($budget);
        $this->assertEquals(150.50, (float) $budget->monthly_limit_usd);
        $this->assertEquals(75, $budget->alert_at_percent);
    }

    public function test_non_admin_or_client_cannot_access_ai_settings(): void
    {
        $editor = User::factory()->withPersonalTeam()->create();
        $editor->assignRole('editor');
        $editor->current_team_id = $editor->currentTeam->id;
        $editor->save();

        $this->actingAs($editor);
        session(['current_team_id' => $editor->currentTeam->id]);

        // Non-admin without admin role
        $response = $this->get(route('admin.ai-settings.index'));
        $response->assertStatus(403);
    }
}
