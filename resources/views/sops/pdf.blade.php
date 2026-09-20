<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $sop->title }} - v{{ $version->version_number ?? 1 }}</title>
    <style>
        @page {
            margin: 25mm 20mm 20mm 20mm;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 6px 0;
        }
        .meta {
            font-size: 10px;
            color: #64748b;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-published {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .badge-draft {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-category {
            background-color: #f1f5f9;
            color: #475569;
        }
        .block {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .heading-block {
            font-size: 15px;
            font-weight: bold;
            color: #1e1b4b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        .text-block {
            color: #334155;
            line-height: 1.6;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            background-color: #ffffff;
        }
        .input-card {
            background-color: #fffbeb;
            border-color: #fef3c7;
        }
        .ai-card {
            background-color: #f5f3ff;
            border-color: #ddd6fe;
        }
        .approval-card {
            background-color: #fff1f2;
            border-color: #fecdd3;
        }
        .handoff-card {
            background-color: #f0fdfa;
            border-color: #ccfbf1;
        }
        .tag-var {
            font-family: monospace;
            background-color: #f1f5f9;
            padding: 1px 4px;
            border-radius: 3px;
            color: #4f46e5;
            font-weight: bold;
        }
        .checklist-item {
            padding: 3px 0;
        }
        .checkbox {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1px solid #94a3b8;
            border-radius: 2px;
            margin-right: 6px;
            vertical-align: middle;
        }
        .footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <div class="footer">
        SOPForge · Procedimiento Operativo Estándar · Documento generado confidencial
    </div>

    <div class="header">
        <div style="float: right;">
            @if(!empty($version) && $version->isPublished())
                <span class="badge badge-published">Versión {{ $version->version_number }} (Publicada)</span>
            @else
                <span class="badge badge-draft">Borrador v{{ $version->version_number ?? 1 }}</span>
            @endif
        </div>

        <h1 class="title">{{ $sop->title }}</h1>

        <div class="meta">
            @if($sop->category)
                <span class="badge badge-category">{{ $sop->category }}</span>
            @endif
            <span>Slug: {{ $sop->slug }}</span> ·
            <span>Generado el: {{ now()->format('d/m/Y H:i') }}</span>
        </div>

        @if($sop->description)
            <p style="margin: 8px 0 0 0; color: #475569; font-style: italic;">
                {{ $sop->description }}
            </p>
        @endif

        @if(!empty($version->changelog))
            <div style="margin-top: 8px; padding: 6px 10px; background: #f8fafc; border-left: 3px solid #6366f1; font-size: 11px;">
                <strong>Notas de versión:</strong> {{ $version->changelog }}
            </div>
        @endif
    </div>

    @php
        $blocks = $version->blocks['blocks'] ?? (is_array($version->blocks) ? $version->blocks : []);
    @endphp

    <div class="content">
        @foreach($blocks as $block)
            @php
                $type = $block['type'] ?? '';
                $props = $block['props'] ?? [];
            @endphp

            <div class="block">
                @if($type === 'heading')
                    <div class="heading-block">{{ $props['text'] ?? 'Sin título' }}</div>

                @elseif($type === 'text')
                    <div class="text-block">
                        {!! nl2br(e(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $props['html'] ?? '')))) !!}
                    </div>

                @elseif($type === 'checklist')
                    <div class="card">
                        <strong style="font-size: 11px; text-transform: uppercase; color: #059669; display: block; margin-bottom: 6px;">
                            Lista de Verificación
                        </strong>
                        @foreach($props['items'] ?? [] as $item)
                            <div class="checklist-item">
                                <span class="checkbox"></span>
                                <span>{{ $item['text'] }}</span>
                                @if(!empty($item['required']))
                                    <span style="color: #e11d48; font-size: 10px;">(Obligatorio)</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                @elseif($type === 'input')
                    <div class="card input-card">
                        <strong style="color: #92400e; font-size: 11px; text-transform: uppercase;">
                            Entrada: {{ $props['label'] ?? 'Campo' }}
                        </strong>
                        <span class="tag-var">&#123;&#123;{{ $props['key'] ?? '' }}&#125;&#125;</span>
                        <div style="margin-top: 4px; font-size: 11px; color: #78350f;">
                            Tipo: <code>{{ $props['field'] ?? 'text' }}</code> ·
                            Responsable: {{ ($props['filled_by'] ?? 'team') === 'client' ? 'Cliente' : 'Equipo' }} ·
                            {{ !empty($props['required']) ? 'Obligatorio' : 'Opcional' }}
                        </div>
                        @if(!empty($props['help']))
                            <div style="margin-top: 3px; font-size: 10px; color: #a16207; font-style: italic;">
                                {{ $props['help'] }}
                            </div>
                        @endif
                    </div>

                @elseif($type === 'media')
                    <div class="card">
                        <strong style="font-size: 11px; text-transform: uppercase; color: #0284c7;">
                            Recurso de Referencia ({{ $props['kind'] ?? 'medio' }})
                        </strong>
                        <div style="margin-top: 4px;">
                            <a href="{{ $props['url'] ?? '#' }}" style="color: #0284c7; text-decoration: underline;">
                                {{ $props['caption'] ?? $props['url'] ?? 'Ver recurso' }}
                            </a>
                        </div>
                    </div>

                @elseif($type === 'decision')
                    <div class="card">
                        <strong style="font-size: 11px; text-transform: uppercase; color: #7e22ce;">
                            Bifurcación Condicional
                        </strong>
                        <p style="margin: 4px 0 6px 0; font-weight: bold;">
                            {{ $props['question'] ?? '¿Pregunta?' }}
                        </p>
                        <ul style="margin: 0; padding-left: 18px;">
                            @foreach($props['branches'] ?? [] as $b)
                                <li>Opción <strong>{{ $b['label'] }}</strong> → Saltar al bloque #{{ $b['goto'] ?? 'siguiente' }}</li>
                            @endforeach
                        </ul>
                    </div>

                @elseif($type === 'ai_task')
                    <div class="card ai-card">
                        <strong style="color: #4f46e5; font-size: 11px; text-transform: uppercase;">
                            Tarea de Inteligencia Artificial: {{ $props['skill_slug'] ?? 'Skill' }}
                        </strong>
                        <div style="margin-top: 4px; font-size: 11px;">
                            Modelo: <code>{{ $props['model'] ?? 'openrouter/auto' }}</code> ·
                            Variable generada: <span class="tag-var">&#123;&#123;{{ $props['output_key'] ?? 'salida' }}&#125;&#125;</span> ·
                            Aprobación previa: {{ !empty($props['requires_approval']) ? 'Requerida' : 'No requerida' }}
                        </div>
                    </div>

                @elseif($type === 'approval')
                    <div class="card approval-card">
                        <strong style="color: #be123c; font-size: 11px; text-transform: uppercase;">
                            Punto de Aprobación Humana
                        </strong>
                        <div style="margin-top: 4px; font-size: 11px; color: #881337;">
                            Rol responsable: <strong>{{ strtoupper($props['role'] ?? 'editor') }}</strong>
                            @if(!empty($props['instructions']))
                                <br>Criterio: {{ $props['instructions'] }}
                            @endif
                        </div>
                    </div>

                @elseif($type === 'handoff')
                    <div class="card handoff-card">
                        <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase;">
                            Traspaso / Handoff a {{ ($props['to'] ?? 'client') === 'client' ? 'Cliente' : 'Equipo' }}
                        </strong>
                        @if(!empty($props['message']))
                            <div style="margin-top: 4px; font-size: 11px; color: #115e59;">
                                Mensaje: {{ $props['message'] }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</body>
</html>
