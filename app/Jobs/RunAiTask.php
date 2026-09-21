<?php

namespace App\Jobs;

use App\Models\AiBudget;
use App\Models\AiCredential;
use App\Models\AiGeneration;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Services\OpenRouter\Contracts\AiProvider;
use App\Support\Sop\VariableResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RunAiTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Backoff delays in seconds for retries.
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $stepId
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job in the background.
     */
    public function handle(AiProvider $provider): void
    {
        /** @var SopRunStep $step */
        $step = SopRunStep::with(['run.sopVersion'])->find($this->stepId);
        if (! $step) {
            return;
        }

        $run = $step->run;
        if (! $run || in_array($step->status, ['completed', 'approved', 'rejected', 'skipped'])) {
            return;
        }

        $versionBlocks = $run->sopVersion->blocks['blocks'] ?? [];
        $blockDef = collect($versionBlocks)->firstWhere('id', $step->block_id) ?? [];
        $props = $blockDef['props'] ?? [];

        $step->update(['status' => 'running']);

        // 1. Control preventivo de presupuesto mensual por equipo
        $budget = AiBudget::where('team_id', $run->team_id)->first();
        if ($budget) {
            $currentMonthCost = (float) AiGeneration::where('team_id', $run->team_id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('status', 'succeeded')
                ->sum('cost_usd');

            if ($currentMonthCost >= (float) $budget->monthly_limit_usd) {
                $errorMsg = "Límite mensual de presupuesto de IA alcanzado (\${$currentMonthCost} / \${$budget->monthly_limit_usd} USD). Tarea en pausa.";

                $step->update([
                    'status' => 'pending',
                    'notes' => $errorMsg,
                ]);

                AiGeneration::create([
                    'team_id' => $run->team_id,
                    'sop_run_id' => $run->id,
                    'sop_run_step_id' => $step->id,
                    'model' => $props['model'] ?? 'unknown',
                    'prompt' => 'N/A - Bloqueado por presupuesto',
                    'status' => 'failed',
                    'error' => $errorMsg,
                ]);

                return;
            }
        }

        // 2. Obtener credencial activa del equipo
        $credential = AiCredential::where('team_id', $run->team_id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $credential) {
            $step->update([
                'status' => 'failed',
                'notes' => 'No hay una credencial activa de OpenRouter configurada en el equipo.',
            ]);
            return;
        }

        // 3. Resolver Skill y versión
        $skillSlug = $props['skill_slug'] ?? '';
        $skill = Skill::where('slug', $skillSlug)->first();

        /** @var SkillVersion|null $skillVersion */
        $skillVersion = null;
        if (! empty($props['skill_version_id'])) {
            $skillVersion = SkillVersion::find($props['skill_version_id']);
        } elseif ($skill) {
            $skillVersion = $skill->currentVersion;
        }

        $systemPrompt = $skillVersion?->instructions ?? 'Eres un asistente experto de marketing digital.';

        // 4. Resolver variables acumuladas (inputs + outputs ya aprobados)
        $availableValues = array_merge($run->inputs ?? [], $run->outputs ?? []);

        $userPromptTemplate = $props['prompt'] ?? null;
        if (empty($userPromptTemplate)) {
            // Si el bloque no define plantilla propia, estructurar datos con las variables del skill
            $skillVars = $skillVersion?->variables ?? [];
            $collectedData = [];
            foreach ($skillVars as $vKey) {
                $collectedData[$vKey] = $availableValues[$vKey] ?? '';
            }
            $userPromptTemplate = "A partir de la siguiente información, genera el entregable solicitado:\n"
                . json_encode($collectedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $resolvedUserPrompt = VariableResolver::resolve($userPromptTemplate, $availableValues, false);

        $model = $props['model'] ?? 'openai/gpt-4o-mini';

        // 5. Registrar registro de generación en estado running
        $generation = AiGeneration::create([
            'team_id' => $run->team_id,
            'sop_run_id' => $run->id,
            'sop_run_step_id' => $step->id,
            'skill_version_id' => $skillVersion?->id,
            'provider' => 'openrouter',
            'model' => $model,
            'prompt' => $resolvedUserPrompt,
            'status' => 'running',
        ]);

        $step->update(['ai_generation_id' => $generation->id]);

        // 6. Invocar proveedor de IA
        try {
            $aiResponse = $provider->generate(
                apiKey: $credential->api_key,
                model: $model,
                systemPrompt: $systemPrompt,
                userPrompt: $resolvedUserPrompt,
                options: [
                    'temperature' => $props['temperature'] ?? 0.4,
                ]
            );

            $credential->update(['last_used_at' => now()]);

            $generation->update([
                'status' => 'succeeded',
                'response' => $aiResponse->content,
                'input_tokens' => $aiResponse->inputTokens,
                'output_tokens' => $aiResponse->outputTokens,
                'cost_usd' => $aiResponse->costUsd,
                'latency_ms' => $aiResponse->latencyMs,
            ]);

            $outputKey = $props['output_key'] ?? 'resultado_ia';
            $requiresApproval = $props['requires_approval'] ?? true;

            $outputData = [
                'content' => $aiResponse->content,
                'output_key' => $outputKey,
                'model' => $aiResponse->model,
                'tokens' => $aiResponse->totalTokens,
                'cost_usd' => $aiResponse->costUsd,
            ];

            if ($requiresApproval) {
                // Requiere aprobación humana: NO se agrega a run->outputs hasta que sea aprobado
                $step->update([
                    'status' => 'awaiting_approval',
                    'output' => $outputData,
                ]);

                $run->update(['status' => 'awaiting_approval']);
            } else {
                // Aprobación automática: el valor queda disponible inmediatamente
                $step->update([
                    'status' => 'completed',
                    'output' => $outputData,
                    'completed_at' => now(),
                ]);

                $outputs = $run->outputs ?? [];
                $outputs[$outputKey] = $aiResponse->content;
                $run->outputs = $outputs;
                $run->save();

                // Intentar encolar siguientes tareas de IA si están listas
                self::dispatchNextEligibleAiTasks($run);
            }
        } catch (Throwable $e) {
            $isNonRetryable = str_contains($e->getMessage(), '401')
                || str_contains($e->getMessage(), '402')
                || str_contains($e->getMessage(), 'cuota')
                || str_contains($e->getMessage(), 'quota')
                || str_contains($e->getMessage(), 'invalid');

            $generation->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $step->update([
                'status' => 'failed',
                'notes' => 'Fallo de IA: ' . $e->getMessage(),
            ]);

            if ($isNonRetryable) {
                Log::error("RunAiTask error fatal no reintentable: {$e->getMessage()}");
                return;
            }

            throw $e;
        }
    }

    /**
     * Look ahead in the SOP run for pending AI tasks whose dependencies are met.
     */
    public static function dispatchNextEligibleAiTasks(SopRun $run): void
    {
        $run->refresh();
        $availableKeys = array_merge(array_keys($run->inputs ?? []), array_keys($run->outputs ?? []));
        $versionBlocks = $run->sopVersion->blocks['blocks'] ?? [];

        $pendingAiSteps = $run->steps()
            ->where('block_type', 'ai_task')
            ->where('status', 'pending')
            ->get();

        foreach ($pendingAiSteps as $aiStep) {
            $blockDef = collect($versionBlocks)->firstWhere('id', $aiStep->block_id) ?? [];
            $props = $blockDef['props'] ?? [];
            $skill = ! empty($props['skill_slug']) ? Skill::where('slug', $props['skill_slug'])->first() : null;
            $requiredVars = $skill?->currentVersion?->variables ?? [];

            // Verificar si todas las variables requeridas existen
            $missing = array_diff($requiredVars, $availableKeys);
            if (empty($missing)) {
                self::dispatch($aiStep->id);
            }
        }
    }
}
