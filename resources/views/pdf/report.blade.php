<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 104px 44px 64px 44px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0f172a; line-height: 1.5; }

        /* Encabezado y pie fijos: se repiten en todas las páginas (modo claro). */
        .page-header { position: fixed; top: -104px; left: -44px; right: -44px; height: 60px; background: #ffffff; border-bottom: 1px solid #e2e8f0; }
        .page-header table { width: 100%; height: 60px; }
        .page-header td { vertical-align: middle; }
        .brand-mark { display: inline-block; width: 11px; height: 11px; background: #23c6c8; margin-right: 9px; }
        .brand-name { font-size: 12px; font-weight: bold; letter-spacing: 1.4px; color: #0f172a; }
        .brand-company { font-size: 9px; color: #64748b; text-align: right; }
        .page-header-line { position: fixed; top: -44px; left: -44px; right: -44px; height: 3px; background: #23c6c8; }
        .page-footer { position: fixed; bottom: -44px; left: 0; right: 0; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 7px; }

        /* Portada del reporte */
        .eyebrow { display: inline-block; font-size: 8.5px; color: #0f7f82; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; background: #e6fbfb; padding: 4px 10px; border-radius: 10px; }
        h1 { font-size: 24px; margin: 12px 0 4px 0; color: #0f172a; letter-spacing: -0.3px; }
        .subtitle { font-size: 11px; color: #64748b; margin: 0 0 14px 0; }

        /* Datos del reporte: tres tarjetas claras */
        table.meta { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px -8px; }
        table.meta td { width: 33%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
        .meta-label { font-size: 7.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.9px; }
        .meta-value { display: block; font-size: 10px; font-weight: bold; color: #0f172a; margin-top: 3px; }

        /* Resumen ejecutivo */
        .summary { background: #f0fdfd; border: 1px solid #ccf3f3; border-left: 4px solid #23c6c8; border-radius: 6px; padding: 12px 14px; margin: 0 0 18px 0; font-size: 10.5px; color: #1e293b; line-height: 1.6; }

        /* Secciones */
        .section { margin-top: 20px; page-break-inside: auto; }
        h2 { page-break-after: avoid; font-size: 13px; margin: 0 0 4px 0; padding-left: 9px; border-left: 4px solid #23c6c8; color: #0f172a; }
        .description { color: #64748b; margin: 0 0 8px 13px; font-size: 9.5px; }

        table.data { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 8px; }
        table.data th { background: #f1f5f9; color: #334155; font-size: 8px; font-weight: bold; text-align: left; padding: 8px 9px; text-transform: uppercase; letter-spacing: 0.6px; border-bottom: 2px solid #23c6c8; }
        table.data td { padding: 7px 9px; border-bottom: 1px solid #eef2f6; font-size: 9px; vertical-align: top; color: #1e293b; }
        table.data tr.alt td { background: #f8fafc; }
        table.data tr:last-child td { border-bottom: none; }
        table.data th.num, table.data td.num { text-align: right; font-variant-numeric: tabular-nums; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .chart { page-break-inside: avoid; margin: 10px 0 12px 0; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; }
        .chart img { width: 100%; }
        .note { font-size: 8.5px; color: #64748b; margin: 7px 0 0 0; }
        .empty { font-size: 9.5px; color: #64748b; padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="page-header">
        <table>
            <tr>
                <td style="padding-left: 44px;"><span class="brand-mark"></span><span class="brand-name">NEXUS ERP</span></td>
                <td class="brand-company" style="padding-right: 44px;">{{ $company }}</td>
            </tr>
        </table>
    </div>
    <div class="page-header-line"></div>
    <div class="page-footer">Generado por Nexus · Documento confidencial de {{ $company }}</div>

    <span class="eyebrow">Reporte</span>
    <h1>{{ $title }}</h1>
    @if (! empty($subtitle))
        <p class="subtitle">{{ $subtitle }}</p>
    @endif

    <table class="meta">
        <tr>
            <td>
                <span class="meta-label">Empresa</span>
                <span class="meta-value">{{ $company }}</span>
            </td>
            <td>
                <span class="meta-label">Generado</span>
                <span class="meta-value">{{ $generatedAt }}</span>
            </td>
            <td>
                <span class="meta-label">Solicitado por</span>
                <span class="meta-value">{{ $author }}</span>
            </td>
        </tr>
    </table>

    @if (! empty($summary))
        <div class="summary">{!! nl2br(e($summary)) !!}</div>
    @endif

    @foreach ($sections as $section)
        <div class="section">
            <h2>{{ $section['heading'] }}</h2>
            @if (! empty($section['description']))
                <p class="description">{{ $section['description'] }}</p>
            @endif

            @if (! empty($section['chart']))
                <div class="chart">
                    <img src="{{ $section['chart'] }}" alt="Gráfica de {{ $section['heading'] }}">
                </div>
            @endif

            @if (empty($section['rows']))
                <div class="empty">No hay registros para esta sección.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            @foreach ($section['columns'] as $index => $column)
                                <th class="{{ $section['numeric'][$index] ? 'num' : '' }}">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $rowIndex => $row)
                            <tr class="{{ $rowIndex % 2 === 1 ? 'alt' : '' }}">
                                @foreach ($row as $index => $cell)
                                    <td class="{{ $section['numeric'][$index] ? 'num' : '' }}">{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if (! empty($section['note']))
                <p class="note">{{ $section['note'] }}</p>
            @endif
        </div>
    @endforeach
</body>
</html>
