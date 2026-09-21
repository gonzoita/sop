<?php

namespace App\Services\Runs;

use App\Models\SopRun;
use App\Models\SopRunStep;
use App\Support\Sop\VariableResolver;
use Illuminate\Support\Str;

class RunDocumentBuilder
{
    /**
     * Build the deliverable Markdown document for an SOP run.
     *
     * @param  SopRun  $run
     * @return string
     */
    public function build(SopRun $run): string
    {
        $run->load(['sop', 'sopVersion', 'client', 'steps.assignee']);

        $version = $run->sopVersion;
        $sopTitle = $run->sop?->title ?? 'SOP';
        $clientName = $run->client?->name ?? 'Sin cliente asignado';
        $versionNumber = $version?->version_number ?? 1;
        $runId = $run->id;
        $status = $run->status;
        $generatedDate = now()->format('Y-m-d H:i');

        // 1. Front-matter con metadatos del entregable
        $yamlSop = json_encode($sopTitle, JSON_UNESCAPED_UNICODE);
        $yamlClient = json_encode($clientName, JSON_UNESCAPED_UNICODE);
        $yamlStatus = json_encode($status, JSON_UNESCAPED_UNICODE);
        $yamlDate = json_encode($generatedDate, JSON_UNESCAPED_UNICODE);

        $doc = "---\n";
        $doc .= "tipo: entregable\n";
        $doc .= "sop: {$yamlSop}\n";
        $doc .= "version_sop: {$versionNumber}\n";
        $doc .= "cliente: {$yamlClient}\n";
        $doc .= "ejecucion_id: {$runId}\n";
        $doc .= "estado: {$yamlStatus}\n";
        $doc .= "fecha_generacion: {$yamlDate}\n";
        $doc .= "---\n\n";

        // Título del documento
        $doc .= "# {$sopTitle}\n\n";

        $blocks = $version?->blocks['blocks'] ?? [];
        /** @var \Illuminate\Database\Eloquent\Collection<string, SopRunStep> $steps */
        $steps = $run->steps->keyBy('block_id');

        // Detectar bloques omitidos (por decisión goto o status == skipped)
        $skippedBlockIds = $this->detectSkippedBlockIds($blocks, $steps);

        // Mapa unificado de valores de entrada y salida
        $values = array_merge($run->inputs ?? [], $run->outputs ?? []);

        $bodySections = [];
        $registryRecords = [];

        foreach ($blocks as $block) {
            $blockId = $block['id'] ?? '';
            $blockType = $block['type'] ?? '';
            $props = $block['props'] ?? [];
            /** @var SopRunStep|null $step */
            $step = $steps->get($blockId);

            // Si el bloque está marcado como omitido o su step es 'skipped', no aparece
            if (isset($skippedBlockIds[$blockId]) || ($step && $step->status === 'skipped')) {
                continue;
            }

            switch ($blockType) {
                case 'heading':
                    $level = max(1, min(6, (int) ($props['level'] ?? 2)));
                    $prefix = str_repeat('#', $level);
                    $rawText = $props['text'] ?? $props['title'] ?? '';
                    $resolved = VariableResolver::resolveDeliverable($rawText, $values);
                    $bodySections[] = "{$prefix} {$resolved}";
                    break;

                case 'text':
                case 'instruction':
                    $rawHtml = $props['html'] ?? $props['content'] ?? $props['text'] ?? '';
                    $resolvedHtml = VariableResolver::resolveDeliverable($rawHtml, $values);
                    $markdownText = $this->htmlToMarkdown($resolvedHtml);
                    if (trim($markdownText) !== '') {
                        $bodySections[] = $markdownText;
                    }
                    break;

                case 'input':
                    $label = $props['label'] ?? $props['key'] ?? 'Campo';
                    $key = $props['key'] ?? '';
                    $val = $values[$key] ?? ($step?->output['value'] ?? null);

                    if ($val === null || $val === '') {
                        $valText = "[sin respuesta: {$key}]";
                    } elseif (is_array($val)) {
                        $valText = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    } else {
                        $valText = (string) $val;
                    }

                    $bodySections[] = "**{$label}**\n\n{$valText}";
                    break;

                case 'checklist':
                    $items = $props['items'] ?? [];
                    $checked = $step?->output['checked_items'] ?? [];
                    $checkLines = [];

                    $title = $props['title'] ?? $props['label'] ?? null;
                    if ($title) {
                        $checkLines[] = "**" . VariableResolver::resolveDeliverable($title, $values) . "**";
                    }

                    foreach ($items as $item) {
                        $itemId = $item['id'] ?? '';
                        $isChecked = in_array($itemId, $checked);
                        $mark = $isChecked ? '[x]' : '[ ]';
                        $rawItemText = $item['text'] ?? $item['label'] ?? '';
                        $resolvedItemText = VariableResolver::resolveDeliverable($rawItemText, $values);
                        $checkLines[] = "- {$mark} {$resolvedItemText}";
                    }

                    if (! empty($checkLines)) {
                        $bodySections[] = implode("\n", $checkLines);
                    }
                    break;

                case 'decision':
                    $label = $props['question'] ?? $props['label'] ?? 'Decisión';
                    $chosen = $step?->output['selected_branch'] ?? null;
                    if ($chosen) {
                        $bodySections[] = "**{$label}**\n\nOpción elegida: {$chosen}";
                    } else {
                        $bodySections[] = "**{$label}**\n\n[sin respuesta: decision]";
                    }
                    break;

                case 'ai_task':
                    $label = $props['label'] ?? 'Entregable de IA';
                    $isApproved = ($step?->status === 'approved')
                        || ($step?->status === 'completed' && ! empty($step?->output['manual_override']));

                    if ($isApproved) {
                        $content = $step->output['content'] ?? $step->output['result'] ?? '';
                        $resolvedContent = VariableResolver::resolveDeliverable($content, $values);
                        $bodySections[] = "**{$label}**\n\n{$resolvedContent}";
                    } else {
                        $bodySections[] = "**{$label}**\n\n> Pendiente de aprobación";
                    }
                    break;

                case 'approval':
                    $label = $props['label'] ?? 'Aprobación requerida';
                    if ($step && $step->status === 'approved') {
                        $who = $step->assignee?->name ?? 'Equipo';
                        $when = $step->completed_at ? $step->completed_at->format('d/m/Y H:i') : 'Confirmado';
                        $registryRecords[] = "- **Aprobación ({$label})**: Aprobado por {$who} el {$when}.";
                    } elseif ($step && $step->status === 'rejected') {
                        $who = $step->assignee?->name ?? 'Equipo';
                        $when = $step->completed_at ? $step->completed_at->format('d/m/Y H:i') : 'Rechazado';
                        $registryRecords[] = "- **Aprobación ({$label})**: Rechazado por {$who} el {$when}.";
                    } else {
                        $registryRecords[] = "- **Aprobación ({$label})**: Pendiente de revisión.";
                    }
                    break;

                case 'handoff':
                    $label = $props['label'] ?? 'Traspaso';
                    $to = $props['to'] ?? ($step?->output['recipient'] ?? 'cliente');
                    if ($step && $step->status === 'completed') {
                        $when = $step->completed_at ? $step->completed_at->format('d/m/Y H:i') : 'Completado';
                        $registryRecords[] = "- **Traspaso ({$label})**: Entregado a '{$to}' el {$when}.";
                    } else {
                        $registryRecords[] = "- **Traspaso ({$label})**: Pendiente de entrega a '{$to}'.";
                    }
                    break;
            }
        }

        // Agregar cuerpo
        if (! empty($bodySections)) {
            $doc .= implode("\n\n", $bodySections) . "\n\n";
        }

        // Agregar sección final Registro si hay registros de approval o handoff
        if (! empty($registryRecords)) {
            $doc .= "## Registro\n\n";
            $doc .= implode("\n", $registryRecords) . "\n";
        }

        return trim($doc) . "\n";
    }

    /**
     * Detect all block IDs that are skipped either directly or by decision branching.
     */
    protected function detectSkippedBlockIds(array $blocks, $steps): array
    {
        $skipped = [];
        $blockIds = array_map(fn ($b) => $b['id'] ?? '', $blocks);

        foreach ($blocks as $idx => $b) {
            $bId = $b['id'] ?? '';
            /** @var SopRunStep|null $step */
            $step = $steps->get($bId);

            if ($step && $step->status === 'skipped') {
                $skipped[$bId] = true;
            }

            if ($step && $step->block_type === 'decision' && ! empty($step->output['goto'])) {
                $gotoId = $step->output['goto'];
                $decisionIdx = $idx;
                $gotoIdx = array_search($gotoId, $blockIds);

                if ($gotoIdx !== false && $gotoIdx > $decisionIdx) {
                    for ($i = $decisionIdx + 1; $i < $gotoIdx; $i++) {
                        if (isset($blockIds[$i])) {
                            $skipped[$blockIds[$i]] = true;
                        }
                    }
                }
            }
        }

        return $skipped;
    }

    /**
     * Convert HTML fragment to clean Markdown without stray tags.
     */
    public function htmlToMarkdown(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = $html;

        // Headers
        for ($i = 6; $i >= 1; $i--) {
            $prefix = str_repeat('#', $i);
            $text = preg_replace("/<h{$i}[^>]*>(.*?)<\/h{$i}>/is", "\n\n{$prefix} $1\n\n", $text);
        }

        // Blockquotes
        $text = preg_replace("/<blockquote[^>]*>(.*?)<\/blockquote>/is", "\n\n> $1\n\n", $text);

        // Pre/Code blocks
        $text = preg_replace("/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is", "\n\n```\n$1\n```\n\n", $text);
        $text = preg_replace("/<code[^>]*>(.*?)<\/code>/is", '`$1`', $text);

        // Paragraphs & line breaks
        $text = preg_replace("/<p[^>]*>(.*?)<\/p>/is", "\n\n$1\n\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Bold & Italic
        $text = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $text);
        $text = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $text);

        // Links
        $text = preg_replace('/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text);

        // List items
        $text = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "\n- $1", $text);
        $text = preg_replace('/<\/?(ul|ol)[^>]*>/is', "\n", $text);

        // Remove all other HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize multiple line breaks
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
