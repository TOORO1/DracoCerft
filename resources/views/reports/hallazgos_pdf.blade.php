<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Reporte de Hallazgos – DracoCert</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; background: #fff; }

        .report-header {
            background: linear-gradient(135deg, #5c6bc0 0%, #3949ab 100%);
            color: #fff;
            padding: 20px 24px;
            display: table;
            width: 100%;
        }
        .report-header .logo-cell { display: table-cell; vertical-align: middle; width: 160px; }
        .report-header .title-cell { display: table-cell; vertical-align: middle; padding-left: 16px; }
        .report-title   { font-size: 20px; font-weight: 700; }
        .report-subtitle { font-size: 11px; opacity: 0.9; margin-top: 2px; }
        .report-meta    { font-size: 9px; opacity: 0.8; margin-top: 6px; }

        .section-title {
            font-size: 12px; font-weight: 700; color: #5c6bc0;
            border-bottom: 2px solid #5c6bc0; padding-bottom: 4px;
            margin: 20px 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px;
        }

        .kpi-row { display: table; width: 100%; margin-bottom: 18px; }
        .kpi-cell { display: table-cell; width: 33.33%; padding: 0 4px; }
        .kpi-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
            padding: 12px 10px; text-align: center;
        }
        .kpi-card.red    { border-top: 3px solid #e53935; }
        .kpi-card.orange { border-top: 3px solid #f57c00; }
        .kpi-card.green  { border-top: 3px solid #43a047; }
        .kpi-value-red    { font-size: 26px; font-weight: 800; color: #e53935; }
        .kpi-value-orange { font-size: 26px; font-weight: 800; color: #f57c00; }
        .kpi-value-green  { font-size: 26px; font-weight: 800; color: #43a047; }
        .kpi-label { font-size: 9px; color: #888; text-transform: uppercase; }

        table.main { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.main th {
            background: #5c6bc0; color: #fff; font-weight: 700;
            padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px;
        }
        table.main td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; font-size: 10px; }
        table.main tr:nth-child(even) td { background: #f3f4ff; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 700; }
        .badge-alta   { background: #fdecea; color: #c62828; }
        .badge-media  { background: #fff3e0; color: #e65100; }
        .badge-baja   { background: #e8f5e9; color: #2e7d32; }

        .report-footer {
            margin-top: 30px; border-top: 1px solid #e0e0e0;
            padding-top: 10px; text-align: center; font-size: 8px; color: #aaa;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="report-header">
        <div class="logo-cell">
            <span style="font-size:22px; font-weight:900; letter-spacing:1px;">🐉 DracoCert</span>
        </div>
        <div class="title-cell">
            <div class="report-title">Reporte de Hallazgos de Auditoría</div>
            <div class="report-subtitle">Sistema de Gestión ISO – Módulo de Auditoría</div>
            <div class="report-meta">Generado: {{ now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY [–] HH:mm') }}</div>
        </div>
    </div>

    {{-- KPI por prioridad --}}
    <div class="section-title">Resumen por Prioridad</div>
    <div class="kpi-row">
        <div class="kpi-cell">
            <div class="kpi-card red">
                <div class="kpi-value-red">{{ $porPrioridad['Alta'] }}</div>
                <div class="kpi-label">Alta Prioridad</div>
            </div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-card orange">
                <div class="kpi-value-orange">{{ $porPrioridad['Media'] }}</div>
                <div class="kpi-label">Media Prioridad</div>
            </div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-card green">
                <div class="kpi-value-green">{{ $porPrioridad['Baja'] }}</div>
                <div class="kpi-label">Baja Prioridad</div>
            </div>
        </div>
    </div>

    {{-- HALLAZGOS --}}
    <div class="section-title">Detalle de Hallazgos</div>
    <table class="main">
        <thead>
            <tr>
                <th style="width:40px;">ID</th>
                <th style="text-align:left;">Título</th>
                <th style="text-align:left;">Descripción</th>
                <th style="width:70px;">Prioridad</th>
                <th style="width:90px;">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($hallazgos as $h)
            <tr>
                <td style="text-align:center;">{{ $h->id }}</td>
                <td style="font-weight:600;">{{ $h->titulo }}</td>
                <td>{{ $h->descripcion ?? '—' }}</td>
                <td style="text-align:center;">
                    @php $p = strtolower($h->prioridad ?? 'media'); @endphp
                    <span class="badge badge-{{ $p }}">{{ ucfirst($h->prioridad ?? 'Media') }}</span>
                </td>
                <td style="text-align:center;">
                    {{ $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y') : '—' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#aaa;padding:16px;">Sin hallazgos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">
        DracoCert — Universidad Antonio José Camacho · Sistema de Gestión ISO ·
        Generado automáticamente el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
    </div>

</body>
</html>
