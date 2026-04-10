<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Reporte de Cumplimiento ISO – DracoCert</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; background: #fff; }

        /* ── Header ─────────────────────────────────────── */
        .report-header {
            background: linear-gradient(135deg, #ff8a00 0%, #e65100 100%);
            color: #fff;
            padding: 20px 24px;
            display: table;
            width: 100%;
        }
        .report-header .logo-cell { display: table-cell; vertical-align: middle; width: 160px; }
        .report-header .title-cell { display: table-cell; vertical-align: middle; padding-left: 16px; }
        .report-title { font-size: 20px; font-weight: 700; letter-spacing: 0.3px; }
        .report-subtitle { font-size: 11px; opacity: 0.9; margin-top: 2px; }
        .report-meta { font-size: 9px; opacity: 0.8; margin-top: 6px; }

        /* ── Section title ───────────────────────────────── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #ff8a00;
            border-bottom: 2px solid #ff8a00;
            padding-bottom: 4px;
            margin: 20px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── KPI Cards ───────────────────────────────────── */
        .kpi-row { display: table; width: 100%; margin-bottom: 18px; border-spacing: 8px; }
        .kpi-cell { display: table-cell; width: 25%; }
        .kpi-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 10px;
            text-align: center;
            border-top: 3px solid #ff8a00;
        }
        .kpi-value { font-size: 28px; font-weight: 800; color: #ff8a00; }
        .kpi-label { font-size: 9px; color: #888; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.4px; }
        .kpi-card.green { border-top-color: #43a047; }
        .kpi-card.green .kpi-value { color: #43a047; }
        .kpi-card.orange { border-top-color: #f57c00; }
        .kpi-card.orange .kpi-value { color: #f57c00; }
        .kpi-card.red { border-top-color: #e53935; }
        .kpi-card.red .kpi-value { color: #e53935; }

        /* ── Compliance bar ──────────────────────────────── */
        .compliance-wrap { display: table; width: 100%; margin-bottom: 16px; }
        .compliance-label { font-size: 10px; font-weight: 700; color: #444; margin-bottom: 4px; }
        .bar-bg { background: #f0f0f0; border-radius: 4px; height: 14px; width: 100%; }
        .bar-fill { height: 14px; border-radius: 4px; }
        .bar-fill.green  { background: #43a047; }
        .bar-fill.orange { background: #f57c00; }
        .bar-fill.red    { background: #e53935; }

        /* ── ISO table ───────────────────────────────────── */
        table.main { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.main th {
            background: #ff8a00;
            color: #fff;
            font-weight: 700;
            padding: 8px 10px;
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.main td {
            padding: 7px 10px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            font-size: 10px;
        }
        table.main tr:nth-child(even) td { background: #fff8f0; }
        table.main td.left { text-align: left; font-weight: 700; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
        }
        .badge-green  { background: #e8f5e9; color: #2e7d32; }
        .badge-orange { background: #fff3e0; color: #e65100; }
        .badge-red    { background: #fdecea; color: #c62828; }

        /* ── Docs expiring ───────────────────────────────── */
        .doc-item { padding: 6px 10px; border-bottom: 1px solid #f5f5f5; display: table; width: 100%; }
        .doc-name { display: table-cell; font-weight: 600; color: #333; }
        .doc-date { display: table-cell; text-align: right; color: #f57c00; font-weight: 700; width: 100px; }

        /* ── Footer ──────────────────────────────────────── */
        .report-footer {
            margin-top: 30px;
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
            text-align: center;
            font-size: 8px;
            color: #aaa;
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
            <div class="report-title">Reporte de Cumplimiento ISO</div>
            <div class="report-subtitle">Sistema de Gestión ISO 9001 · 14001 · 27001</div>
            <div class="report-meta">Generado: {{ now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY [–] HH:mm') }}</div>
        </div>
    </div>

    {{-- KPI RESUMEN --}}
    <div class="section-title">Resumen Ejecutivo</div>
    <div class="kpi-row">
        <div class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-value">{{ $totalDocs }}</div>
                <div class="kpi-label">Total Documentos</div>
            </div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-card green">
                <div class="kpi-value">{{ $docsVigentes }}</div>
                <div class="kpi-label">Vigentes</div>
            </div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-card orange">
                <div class="kpi-value">{{ $docsPorVencer }}</div>
                <div class="kpi-label">Por Vencer (30 días)</div>
            </div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-card red">
                <div class="kpi-value">{{ $docsCaducados }}</div>
                <div class="kpi-label">Caducados</div>
            </div>
        </div>
    </div>

    {{-- BARRA GLOBAL --}}
    <div style="margin-bottom: 20px;">
        <div class="compliance-label">Cumplimiento Global: <strong>{{ $globalCompliance }}%</strong></div>
        <div class="bar-bg">
            @php $barClass = $globalCompliance >= 80 ? 'green' : ($globalCompliance >= 50 ? 'orange' : 'red'); @endphp
            <div class="bar-fill {{ $barClass }}" style="width: {{ $globalCompliance }}%;"></div>
        </div>
    </div>

    {{-- CUMPLIMIENTO POR NORMA --}}
    <div class="section-title">Cumplimiento por Norma ISO</div>
    <table class="main">
        <thead>
            <tr>
                <th style="text-align:left;">Norma</th>
                <th>Fuente</th>
                <th>Total</th>
                <th>Conformes / Vigentes</th>
                <th>No Conf. / Por Vencer</th>
                <th>Obs. / Caducados</th>
                <th>N/A</th>
                <th>% Cumplimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($isoData as $row)
            @php
                $esEval  = ($row['fuente'] ?? 'documentos') === 'evaluacion';
                $pClass  = $row['compliance'] >= 80 ? 'green' : ($row['compliance'] >= 50 ? 'orange' : 'red');
                $pColor  = $pClass === 'green' ? '#2e7d32' : ($pClass === 'orange' ? '#e65100' : '#c62828');
            @endphp
            <tr>
                <td class="left">{{ $row['norma'] }}</td>
                <td>
                    @if($esEval)
                        <span class="badge badge-green">Evaluación ISO</span>
                    @else
                        <span class="badge" style="background:#e3f2fd;color:#1565c0;">Documentos</span>
                    @endif
                </td>
                {{-- Total --}}
                <td>{{ $esEval ? $row['total_cl'] : $row['total'] }}</td>
                {{-- Conformes / Vigentes --}}
                <td>
                    <span class="badge badge-green">
                        {{ $esEval ? $row['conformes'] : $row['vigente'] }}
                    </span>
                </td>
                {{-- No Conformes / Por Vencer --}}
                <td>
                    <span class="badge badge-red">
                        {{ $esEval ? $row['no_conformes'] : $row['por_vencer'] }}
                    </span>
                </td>
                {{-- Observaciones / Caducados --}}
                <td>
                    <span class="badge badge-orange">
                        {{ $esEval ? $row['observaciones'] : $row['caducado'] }}
                    </span>
                </td>
                {{-- N/A (solo evaluación) --}}
                <td>{{ $esEval ? $row['na'] : '—' }}</td>
                {{-- % Cumplimiento --}}
                <td>
                    <strong style="color: {{ $pColor }};">{{ $row['compliance'] }}%</strong>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center; color:#aaa; padding:16px;">Sin datos de normas registradas</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- DOCUMENTOS POR VENCER --}}
    @if(count($docsExpiring) > 0)
    <div class="section-title">⚠ Documentos por Vencer (próximos 30 días)</div>
    <div style="border: 1px solid #ffe0b2; border-radius: 6px; overflow: hidden; margin-bottom: 16px;">
        @foreach($docsExpiring as $doc)
        <div class="doc-item" style="{{ $loop->last ? 'border-bottom:none;' : '' }}">
            <span class="doc-name">{{ $doc->Nombre_Doc }}</span>
            <span class="doc-date">{{ \Carbon\Carbon::parse($doc->Fecha_Caducidad)->format('d/m/Y') }}</span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- LISTADO DOCUMENTOS --}}
    <div class="section-title">Inventario de Documentos</div>
    <table class="main">
        <thead>
            <tr>
                <th>ID</th>
                <th style="text-align:left;">Nombre</th>
                <th>Norma</th>
                <th>Versión</th>
                <th>Vencimiento</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documentos as $doc)
            <tr>
                <td>{{ $doc->idDocumento }}</td>
                <td class="left">{{ \Str::limit($doc->Nombre_Doc, 45) }}</td>
                <td>{{ $doc->norma }}</td>
                <td>{{ $doc->numero_Version ? number_format($doc->numero_Version, 1) : '1.0' }}</td>
                <td>{{ $doc->Fecha_Caducidad ? \Carbon\Carbon::parse($doc->Fecha_Caducidad)->format('d/m/Y') : '—' }}</td>
                <td>
                    @php
                        $e = $doc->estado;
                        $bc = $e === 'Vigente' ? 'green' : ($e === 'Por Vencer' ? 'orange' : 'red');
                    @endphp
                    <span class="badge badge-{{ $bc }}">{{ $e }}</span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:12px;">Sin documentos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="report-footer">
        DracoCert — Universidad Antonio José Camacho · Sistema de Gestión ISO ·
        Generado automáticamente el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
    </div>

</body>
</html>
