<?php

namespace Tests\Feature;

use App\Actions\Sop\AdvanceRun;
use App\Actions\Sop\StartSopRun;
use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Models\SopVersion;
use App\Models\User;
use App\Notifications\OverdueStepReminderNotification;
use App\Notifications\RunCompletedNotification;
use App\Notifications\StepAssignedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SopNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_step_assignment_dispatches_step_assigned_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;
        session(['current_team_id' => $team->id]);

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'SOP Notificaciones', 'slug' => 'sop-notif']);
        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => [
                ['id' => 'b1', 'type' => 'input', 'props' => ['key' => 'd1', 'field' => 'text']],
            ]],
            'published_at' => now(),
        ]);
        $sop->current_version_id = $version->id;
        $sop->save();

        $run = app(StartSopRun::class)->execute($admin, $sop, [
            'sop_version_id' => $version->id,
            'title' => 'Corrida Notificación Asignación',
        ]);
        $step = $run->steps->firstWhere('block_id', 'b1');

        $this->actingAs($admin);

        $response = $this->put(route('runs.steps.assignment', ['run' => $run->id, 'step' => $step->id]), [
            'assigned_to' => $collaborator->id,
            'due_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $response->assertRedirect();

        Notification::assertSentTo(
            $collaborator,
            StepAssignedNotification::class,
            function ($notification) use ($run, $step) {
                return $notification->run->id === $run->id && $notification->step->id === $step->id;
            }
        );
    }

    public function test_run_completion_dispatches_run_completed_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $admin->current_team_id = $admin->currentTeam->id;
        $admin->save();
        $team = $admin->currentTeam;
        session(['current_team_id' => $team->id]);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'SOP Finalización', 'slug' => 'sop-fin']);
        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => [
                ['id' => 'b1', 'type' => 'input', 'props' => ['key' => 'final', 'field' => 'text']],
            ]],
            'published_at' => now(),
        ]);
        $sop->current_version_id = $version->id;
        $sop->save();

        $run = app(StartSopRun::class)->execute($admin, $sop, [
            'sop_version_id' => $version->id,
            'title' => 'Corrida a Completar',
        ]);
        $step = $run->steps->firstWhere('block_id', 'b1');

        // Completar paso finalizando la corrida
        app(AdvanceRun::class)->execute($run, $step, ['value' => 'Listo'], $admin);

        Notification::assertSentTo(
            $admin,
            RunCompletedNotification::class,
            function ($notification) use ($run) {
                return $notification->run->id === $run->id;
            }
        );
    }

    public function test_check_overdue_steps_command_sends_reminders_and_updates_timestamp(): void
    {
        Notification::fake();

        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;

        $assignee = User::factory()->create();
        $team->users()->attach($assignee, ['role' => 'editor']);

        $sop = Sop::create(['team_id' => $team->id, 'title' => 'SOP Vencido', 'slug' => 'sop-vencido']);
        $version = SopVersion::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'version_number' => 1,
            'blocks' => ['schema_version' => 1, 'blocks' => []],
        ]);

        $run = SopRun::create([
            'team_id' => $team->id,
            'sop_id' => $sop->id,
            'sop_version_id' => $version->id,
            'title' => 'Corrida con Paso Vencido',
            'status' => 'in_progress',
        ]);

        $step = SopRunStep::create([
            'sop_run_id' => $run->id,
            'block_id' => 'b_overdue',
            'block_type' => 'input',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
            'due_at' => now()->subHours(3), // Vencido hace 3 horas
        ]);

        $this->artisan('sop:check-overdue-steps')
            ->expectsOutput('Se enviaron 1 recordatorios de pasos vencidos a la cola.')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $assignee,
            OverdueStepReminderNotification::class,
            function ($notification) use ($run, $step) {
                return $notification->run->id === $run->id && $notification->step->id === $step->id;
            }
        );

        $this->assertNotNull($step->fresh()->last_reminded_at);

        // Si se corre de nuevo inmediatamente, no debe enviar duplicado
        Notification::fake();
        $this->artisan('sop:check-overdue-steps')
            ->expectsOutput('No se encontraron pasos vencidos pendientes de recordatorio.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
