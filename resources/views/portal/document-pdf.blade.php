<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->title }}</title>
    <style>
        @page {
            margin: 28mm 20mm 22mm 20mm;
        }

        body {
            font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #1e293b;
            background: #ffffff;
        }

        header {
            position: fixed;
            top: -20mm;
            left: 0;
            right: 0;
            height: 14mm;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-meta {
            font-size: 8pt;
            color: #94a3b8;
            text-align: right;
        }

        footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 8pt;
            color: #94a3b8;
            text-align: center;
        }

        .doc-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #6366f1;
        }

        .doc-title {
            font-size: 20pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }

        .doc-subtitle {
            font-size: 9pt;
            color: #64748b;
        }

        /* Typography for parsed Markdown */
        h1 {
            font-size: 15pt;
            color: #1e293b;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }

        h2 {
            font-size: 13pt;
            color: #334155;
            margin-top: 18px;
            margin-bottom: 8px;
        }

        h3 {
            font-size: 11pt;
            color: #475569;
            margin-top: 14px;
            margin-bottom: 6px;
        }

        p {
            margin-top: 0;
            margin-bottom: 10px;
        }

        ul, ol {
            margin-top: 0;
            margin-bottom: 10px;
            padding-left: 20px;
        }

        li {
            margin-bottom: 4px;
        }

        blockquote {
            margin: 12px 0;
            padding: 8px 14px;
            background: #f8fafc;
            border-left: 3.5px solid #6366f1;
            color: #475569;
            font-style: italic;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 16px;
            font-size: 9pt;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }

        code {
            font-family: monospace;
            background: #f1f5f9;
            color: #0f172a;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8.5pt;
        }

        pre {
            background: #0f172a;
            color: #f8fafc;
            padding: 10px;
            border-radius: 6px;
            font-size: 8.5pt;
            overflow-x: auto;
            margin-bottom: 12px;
        }

        pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }

        hr {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td class="header-title" style="border: none; padding: 0;">
                    {{ $document->client->name ?? 'Entregable' }} &bull; Documento Oficial
                </td>
                <td class="header-meta" style="border: none; padding: 0;">
                    Fecha: {{ $document->published_at?->format('d/m/Y') }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Documento generado para {{ $document->client->name ?? 'el cliente' }} &bull; Confidencial
    </footer>

    <main>
        <div class="doc-header">
            <h1 class="doc-title">{{ $document->title }}</h1>
            <div class="doc-subtitle">
                Procedimiento: <strong>{{ $document->sopRun?->sop?->title ?? 'SOP' }}</strong> &bull;
                Fecha de publicación: <strong>{{ $document->published_at?->format('d/m/Y H:i') }}</strong>
            </div>
        </div>

        <div class="doc-content">
            {!! $htmlContent !!}
        </div>
    </main>
</body>
</html>
