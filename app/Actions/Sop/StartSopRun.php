<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Models\SopVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartSopRun
{
    /**
     * Block types that generate actionable steps in an execution run.
     */
    public const EXECUTABLE_TYPES = [
        'input',
        'checklist',
        'decision',
        'ai_task',
        'approval',
        'handoff',
    ];

    /**
     * Start a new SOP run freezing the specified or latest published version.
     *
     * @param  User  $user
     * @param  Sop  $sop
     * @param  array  $data
     * @return SopRun
     */
    public function execute(User $user, Sop $sop, array $data = []): SopRun
    {
        // 1. Resolve version to freeze
        if (! empty($data['sop_version_id'])) {
            /** @var SopVersion $version */
            $version = $sop->versions()->where('id', $data['sop_version_id'])->firstOrFail();
        } else {
            $version = $sop->currentVersion;
        }

        if (! $version) {
            throw new InvalidArgumentException('El SOP no tiene una versión disponible para iniciar la ejecución.');
        }

        return DB::transaction(function () use ($user, $sop, $version, $data) {
            $title = ! empty($data['title'])
                ? $data['title']
                : "{$sop->title} - " . now()->format('d/m/Y H:i');

            $teamId = $user->current_team_id ?? $sop->team_id;

            // 2. Create SopRun
            /** @var SopRun $run */
            $run = SopRun::create([
                'team_id' => $teamId,
                'sop_id' => $sop->id,
                'sop_version_id' => $version->id,
                'client_id' => $data['client_id'] ?? null,
                'title' => $title,
                'status' => 'in_progress',
                'inputs' => $data['inputs'] ?? [],
                'outputs' => [],
                'started_by' => $user->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'started_at' => now(),
            ]);

            // 3. Create executable steps
            $blocks = $version->blocks['blocks'] ?? [];
            foreach ($blocks as $block) {
                $blockType = $block['type'] ?? '';
                $blockId = $block['id'] ?? '';

                if (in_array($blockType, self::EXECUTABLE_TYPES)) {
                    SopRunStep::create([
                        'sop_run_id' => $run->id,
                        'block_id' => $blockId,
                        'block_type' => $blockType,
                        'status' => 'pending',
                        'assigned_to' => $data['assigned_to'] ?? null,
                        'output' => null,
                        'due_at' => ! empty($data['due_at']) ? $data['due_at'] : null,
                    ]);
                }
            }

            $freshRun = $run->fresh(['steps', 'sop', 'sopVersion', 'client']);

            // Encolar tareas de IA que tengan sus dependencias listas
            \App\Jobs\RunAiTask::dispatchNextEligibleAiTasks($freshRun);

            return $freshRun;
        });
    }
}
