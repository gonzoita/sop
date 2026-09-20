<?php

namespace App\Actions\Sop;

use App\Models\Sop;
use App\Models\SopVersion;

class ExportSopToMarkdown
{
    /**
     * Export an SOP version to Markdown.
     */
    public function execute(Sop $sop, ?SopVersion $version = null): string
    {
        $v = $version ?? $sop->currentVersion ?? $sop->versions()->latest('version_number')->first();
        $blocks = $v?->blocks['blocks'] ?? (is_array($v?->blocks) ? $v->blocks : []);

        $md = [];
        $md[] = "# {$sop->title}";
        if ($sop->category) {
            $md[] = "**Categoría:** {$sop->category}";
        }
        if ($sop->description) {
            $md[] = "_{$sop->description}_";
        }

        $versionStr = $v
            ? "v{$v->version_number}" . ($v->isPublished() ? " (Publicada el " . $v->published_at->format('d/m/Y') . ")" : " (Borrador)")
            : 'Borrador inicial';
        $md[] = "**Versión:** {$versionStr}";

        if ($v?->changelog) {
            $md[] = "**Notas de versión:** {$v->changelog}";
        }
        $md[] = "\n---\n";

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $props = $block['props'] ?? [];

            switch ($type) {
                case 'heading':
                    $text = $props['text'] ?? 'Sin título';
                    $md[] = "## {$text}\n";
                    break;

                case 'text':
                    $html = $props['html'] ?? '';
                    $clean = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));
                    $md[] = trim($clean) . "\n";
                    break;

                case 'checklist':
                    $items = $props['items'] ?? [];
                    foreach ($items as $item) {
                        $req = ! empty($item['required']) ? ' *(Obligatorio)*' : '';
                        $md[] = "- [ ] {$item['text']}{$req}";
                    }
                    $md[] = '';
                    break;

                case 'input':
                    $label = $props['label'] ?? 'Campo';
                    $key = $props['key'] ?? '';
                    $field = $props['field'] ?? 'text';
                    $by = ($props['filled_by'] ?? 'team') === 'client' ? 'Cliente' : 'Equipo';
                    $req = ! empty($props['required']) ? 'Requerido' : 'Opcional';
                    $md[] = "> 📥 **Campo: {$label}** (`{{{$key}}}`)  ";
                    $md[] = "> *Tipo:* `{$field}` | *Responsable:* {$by} | *Estado:* {$req}\n";
                    break;

                case 'media':
                    $kind = $props['kind'] ?? 'recurso';
                    $url = $props['url'] ?? '';
                    $caption = $props['caption'] ?? $url;
                    $md[] = "📎 **Recurso ({$kind}):** [{$caption}]({$url})\n";
                    break;

                case 'decision':
                    $q = $props['question'] ?? '';
                    $md[] = "❓ **Decisión condicional:** {$q}";
                    foreach ($props['branches'] ?? [] as $branch) {
                        $goto = $branch['goto'] ?? 'siguiente';
                        $md[] = "  - Opción **{$branch['label']}** → Ir a bloque `#{$goto}`";
                    }
                    $md[] = '';
                    break;

                case 'ai_task':
                    $skill = $props['skill_slug'] ?? 'Skill';
                    $model = $props['model'] ?? 'openrouter/auto';
                    $output = $props['output_key'] ?? 'salida';
                    $appr = ! empty($props['requires_approval']) ? 'Sí' : 'No';
                    $md[] = "> 🤖 **Tarea de IA:** `{$skill}`";
                    $md[] = "> *Modelo:* `{$model}` | *Salida:* `{{{$output}}}` | *Requiere Aprobación:* {$appr}\n";
                    break;

                case 'approval':
                    $role = $props['role'] ?? 'editor';
                    $inst = $props['instructions'] ?? '';
                    $md[] = "🛡️ **Punto de Aprobación Humana:** Rol `{$role}`";
                    if ($inst) {
                        $md[] = "> *Criterio:* {$inst}\n";
                    }
                    break;

                case 'handoff':
                    $to = ($props['to'] ?? 'client') === 'client' ? 'Cliente' : 'Equipo';
                    $msg = $props['message'] ?? '';
                    $md[] = "⇄ **Entrega (Handoff) a {$to}:** {$msg}\n";
                    break;
            }
        }

        return implode("\n", $md);
    }
}
