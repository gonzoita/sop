<?php

namespace App\Actions\Sop;

use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdvanceRun
{
    /**
     * Advance an SOP run by completing or updating a specific step.
     *
     * @param  SopRun  $run
     * @param  SopRunStep  $step
     * @param  array  $data
     * @param  User|null  $user
     * @return SopRun
     */
    public function execute(SopRun $run, SopRunStep $step, array $data = [], ?User $user = null): SopRun
    {
        if ($step->sop_run_id !== $run->id) {
            throw new InvalidArgumentException('El paso indicado no pertenece a esta ejecución de SOP.');
        }

        return DB::transaction(function () use ($run, $step, $data) {
            $versionBlocks = $run->sopVersion->blocks['blocks'] ?? [];
            $blockDef = collect($versionBlocks)->firstWhere('id', $step->block_id) ?? [];
            $props = $blockDef['props'] ?? [];

            switch ($step->block_type) {
                case 'input':
                    $key = $props['key'] ?? null;
                    $value = $data['value'] ?? null;

                    $step->output = [
                        'key' => $key,
                        'value' => $value,
                    ];
                    $step->status = 'completed';
                    $step->completed_at = now();

                    if ($key !== null) {
                        $inputs = $run->inputs ?? [];
                        $inputs[$key] = $value;
                        $run->inputs = $inputs;
                    }
                    break;

                case 'checklist':
                    $checkedItems = $data['checked_items'] ?? [];
                    $items = $props['items'] ?? [];
                    $requiredIds = collect($items)
                        ->filter(fn ($item) => ! empty($item['required']))
                        ->pluck('id')
                        ->all();

                    $allRequiredDone = empty(array_diff($requiredIds, $checkedItems));

                    $step->output = [
                        'checked_items' => $checkedItems,
                    ];
                    $step->status = $allRequiredDone ? 'completed' : 'pending';
                    $step->completed_at = $allRequiredDone ? now() : null;
                    break;

                case 'decision':
                    $selectedBranch = $data['selected_branch'] ?? null;
                    $branches = $props['branches'] ?? [];

                    $chosen = collect($branches)->first(function ($b) use ($selectedBranch, $data) {
                        return ($b['label'] ?? '') === $selectedBranch
                            || ($b['goto'] ?? '') === ($data['goto'] ?? '');
                    });

                    if (! $chosen) {
                        throw new InvalidArgumentException('La opción de decisión seleccionada no es válida.');
                    }

                    $gotoId = $chosen['goto'] ?? null;

                    $step->output = [
                        'selected_branch' => $chosen['label'] ?? '',
                        'goto' => $gotoId,
                    ];
                    $step->status = 'completed';
                    $step->completed_at = now();

                    // Re-calculate branching: skip blocks between decision and goto
                    $this->applyDecisionBranching($run, $step->block_id, $gotoId, $versionBlocks);
                    break;

                case 'ai_task':
                    // In Fase 2, AI task can be completed manually
                    $outputVal = $data['output'] ?? $data['value'] ?? null;
                    $outputKey = $props['output_key'] ?? null;

                    $step->output = [
                        'manual_override' => true,
                        'result' => $outputVal,
                    ];
                    $step->status = 'completed';
                    $step->completed_at = now();

                    if ($outputKey !== null) {
                        $outputs = $run->outputs ?? [];
                        $outputs[$outputKey] = $outputVal;
                        $run->outputs = $outputs;
                    }
                    break;

                case 'approval':
                    $decision = $data['decision'] ?? 'approved';
                    if (! in_array($decision, ['approved', 'rejected'])) {
                        throw new InvalidArgumentException("La decisión de aprobación debe ser 'approved' o 'rejected'.");
                    }

                    $step->status = $decision;
                    $step->completed_at = now();
                    if (! empty($data['notes'])) {
                        $step->notes = $data['notes'];
                    }
                    break;

                case 'handoff':
                    $step->output = [
                        'acknowledged_at' => now()->toIso8601String(),
                        'recipient' => $props['to'] ?? 'client',
                    ];
                    $step->status = 'completed';
                    $step->completed_at = now();
                    break;

                default:
                    $step->status = 'completed';
                    $step->completed_at = now();
                    break;
            }

            $step->save();

            $previousStatus = $run->status;

            // Recalculate run status
            $this->recalculateRunStatus($run);

            $run->save();

            if ($step->status === 'approved') {
                \App\Events\RunStepApproved::dispatch($run, $step);
            }

            if ($previousStatus !== 'completed' && $run->status === 'completed') {
                \App\Events\RunCompleted::dispatch($run);
            }

            return $run->fresh(['steps', 'sop', 'sopVersion', 'client']);
        });
    }

    /**
     * Evaluate decision branching and mark bypassed blocks as skipped.
     */
    protected function applyDecisionBranching(SopRun $run, string $decisionBlockId, ?string $gotoBlockId, array $versionBlocks): void
    {
        if (! $gotoBlockId) {
            return;
        }

        $blockIds = collect($versionBlocks)->pluck('id')->all();
        $decisionIdx = array_search($decisionBlockId, $blockIds);
        $gotoIdx = array_search($gotoBlockId, $blockIds);

        if ($decisionIdx === false || $gotoIdx === false || $gotoIdx <= $decisionIdx) {
            return;
        }

        // Steps between decision and goto
        $skippedBlockIds = array_slice($blockIds, $decisionIdx + 1, $gotoIdx - $decisionIdx - 1);

        if (! empty($skippedBlockIds)) {
            SopRunStep::where('sop_run_id', $run->id)
                ->whereIn('block_id', $skippedBlockIds)
                ->where('status', '!=', 'completed')
                ->update(['status' => 'skipped']);
        }

        // Ensure the goto step itself is not skipped
        SopRunStep::where('sop_run_id', $run->id)
            ->where('block_id', $gotoBlockId)
            ->where('status', 'skipped')
            ->update(['status' => 'pending']);
    }

    /**
     * Recalculate the overall status of the SOP run based on its steps.
     */
    protected function recalculateRunStatus(SopRun $run): void
    {
        $steps = $run->steps()->get();

        if ($steps->isEmpty()) {
            $run->status = 'completed';
            $run->completed_at = now();
            return;
        }

        $hasAwaitingApproval = $steps->contains(fn ($s) => $s->status === 'awaiting_approval');
        if ($hasAwaitingApproval) {
            $run->status = 'awaiting_approval';
            return;
        }

        // Check if all non-skipped steps are completed or approved
        $allDone = $steps->every(function ($s) {
            return in_array($s->status, ['completed', 'approved', 'skipped']);
        });

        if ($allDone) {
            $run->status = 'completed';
            $run->completed_at = $run->completed_at ?? now();
        } else {
            $run->status = 'in_progress';
        }
    }
}
