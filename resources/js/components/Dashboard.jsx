import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import {
    AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend,
    ResponsiveContainer, RadialBarChart, RadialBar,
} from 'recharts';
import Sidebar from './Sidebar.jsx';
import Header from './Header.jsx';

/* ══════════════════════════════════════════════════════════════
 |  Hook: contador animado
 ══════════════════════════════════════════════════════════════ */
function useCountUp(target, duration = 1200) {
    const [count, setCount] = useState(0);
    useEffect(() => {
        if (!target) return;
        let frame;
        let start = null;
        const step = (ts) => {
            if (!start) start = ts;
            const progress = Math.min((ts - start) / duration, 1);
            setCount(Math.floor(progress * target));
            if (progress < 1) frame = requestAnimationFrame(step);
            else setCount(target);
        };
        frame = requestAnimationFrame(step);
        return () => cancelAnimationFrame(frame);
    }, [target, duration]);
    return count;
}

/* ══════════════════════════════════════════════════════════════
 |  KPI Card con animación
 ══════════════════════════════════════════════════════════════ */
const KPICard = ({ icon, label, value = 0, color, subtitle, trend }) => {
    const animValue = useCountUp(value);
    return (
        <div className="kpi-card" style={{ '--kpi-color': color, borderTopColor: color }}>
            <div className="kpi-icon-wrap" style={{ background: color + '18', color }}>
                <i className={`fa ${icon}`} />
            </div>
            <div className="kpi-body">
                <div className="kpi-value">{animValue.toLocaleString('es-CO')}</div>
                <div className="kpi-label">{label}</div>
                {subtitle && <div className="kpi-subtitle">{subtitle}</div>}
            </div>
            {trend !== undefined && (
                <div className={`kpi-trend ${trend >= 0 ? 'up' : 'down'}`}>
                    <i className={`fa fa-arrow-${trend >= 0 ? 'up' : 'down'}`} />
                    {Math.abs(trend)}%
                </div>
            )}
        </div>
    );
};

/* ══════════════════════════════════════════════════════════════
 |  Colores ISO
 ══════════════════════════════════════════════════════════════ */
function isoColor(codigo = '') {
    if (codigo.includes('9001'))  return '#ff8a00';
    if (codigo.includes('14001')) return '#26a69a';
    if (codigo.includes('27001')) return '#5c6bc0';
    return '#78909c';
}

/* ══════════════════════════════════════════════════════════════
 |  Tooltip personalizado para Recharts
 ══════════════════════════════════════════════════════════════ */
const CustomTooltip = ({ active, payload, label, unit = '' }) => {
    if (!active || !payload?.length) return null;
    return (
        <div style={{
            background: '#1a1a2e', color: '#fff', borderRadius: 10,
            padding: '10px 14px', fontSize: 13, boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
        }}>
            <div style={{ fontWeight: 700, marginBottom: 6, color: '#ff8a00' }}>{label}</div>
            {payload.map((p, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <div style={{ width: 8, height: 8, borderRadius: '50%', background: p.color || p.fill }} />
                    <span style={{ color: '#aaa' }}>{p.name}:</span>
                    <span style={{ fontWeight: 700 }}>{p.value}{unit}</span>
                </div>
            ))}
        </div>
    );
};

/* ══════════════════════════════════════════════════════════════
 |  Widget: alerta de documento por vencer
 ══════════════════════════════════════════════════════════════ */
const ExpiringDocItem = ({ doc }) => {
    const urgent  = doc.dias <= 7;
    const warning = doc.dias <= 15;
    const color   = urgent ? '#e53935' : warning ? '#f57c00' : '#ff8a00';
    const bg      = urgent ? '#fdecea' : warning ? '#fff8e1' : '#fff3e0';

    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: 12,
            padding: '10px 14px', borderRadius: 8,
            background: bg, marginBottom: 8,
            border: `1px solid ${color}22`,
        }}>
            <div style={{
                width: 40, height: 40, borderRadius: 8, background: `${color}18`,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                color, fontSize: 16, flexShrink: 0,
            }}>
                <i className="fa fa-file-alt" />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: '#333', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {doc.nombre}
                </div>
                <div style={{ fontSize: 11, color: '#888', marginTop: 2 }}>
                    Vence: {new Date(doc.vence).toLocaleDateString('es-CO', { dateStyle: 'medium' })}
                </div>
            </div>
            <div style={{
                background: color, color: '#fff', borderRadius: 20,
                padding: '3px 10px', fontSize: 11, fontWeight: 800, whiteSpace: 'nowrap',
            }}>
                {doc.dias}d
            </div>
        </div>
    );
};

/* ══════════════════════════════════════════════════════════════
 |  Íconos de módulo para el log de actividad
 ══════════════════════════════════════════════════════════════ */
const MODULE_CFG = {
    auth:           { color: '#5c6bc0', bg: '#ede7f6', icon: 'fa-sign-in-alt' },
    documentos:     { color: '#26a69a', bg: '#e0f2f1', icon: 'fa-file-alt' },
    auditoria:      { color: '#ff8a00', bg: '#fff3e0', icon: 'fa-clipboard-check' },
    usuarios:       { color: '#ef5350', bg: '#fdecea', icon: 'fa-users' },
    capacitaciones: { color: '#42a5f5', bg: '#e3f2fd', icon: 'fa-graduation-cap' },
};

/* ══════════════════════════════════════════════════════════════
 |  Botón de exportación
 ══════════════════════════════════════════════════════════════ */
function ExportBtn({ href, icon, label, color = '#ff8a00' }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            style={{
                display: 'inline-flex', alignItems: 'center', gap: 6,
                padding: '7px 14px', borderRadius: 8, textDecoration: 'none',
                background: color + '15', color, border: `1.5px solid ${color}40`,
                fontSize: 12, fontWeight: 700, transition: 'all 0.2s',
                whiteSpace: 'nowrap',
            }}
            onMouseEnter={e => {
                e.currentTarget.style.background = color;
                e.currentTarget.style.color = '#fff';
            }}
            onMouseLeave={e => {
                e.currentTarget.style.background = color + '15';
                e.currentTarget.style.color = color;
            }}
        >
            <i className={`fa ${icon}`} />
            <span>{label}</span>
        </a>
    );
}

/* ══════════════════════════════════════════════════════════════
 |  Componente principal Dashboard
 ══════════════════════════════════════════════════════════════ */
export default function Dashboard() {
    const [sidebarOpen,   setSidebarOpen]   = useState(() => window.innerWidth > 700);
    const [stats,         setStats]         = useState(null);
    const [loading,       setLoading]       = useState(true);
    const [statsError,    setStatsError]    = useState(false);
    const [sendingAlert,  setSendingAlert]  = useState(false);
    // Diferir render de gráficos hasta que el hilo principal esté libre
    // → KPIs y cabecera se pintan ~300-500 ms antes (mejora LCP)
    const [chartsReady,   setChartsReady]   = useState(false);

    const currentRole = typeof window !== 'undefined' ? (window.currentRole || '') : '';

    const loadStats = () => {
        setLoading(true);
        setStatsError(false);
        axios.get('/api/dashboard/stats')
            .then(r => setStats(r.data))
            .catch(e => {
                console.error(e);
                setStatsError(true);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadStats();
        // Permitir que el navegador termine el primer paint antes de renderizar Recharts
        const ric = typeof requestIdleCallback !== 'undefined'
            ? requestIdleCallback(() => setChartsReady(true), { timeout: 1500 })
            : setTimeout(() => setChartsReady(true), 200);
        return () => typeof requestIdleCallback !== 'undefined'
            ? cancelIdleCallback(ric)
            : clearTimeout(ric);
    }, []);

    const handleEnviarAlerta = async () => {
        setSendingAlert(true);
        try {
            const res = await axios.post('/api/alertas/enviar-vencimientos');
            Swal.fire({
                icon: res.data.ok ? 'success' : 'warning',
                title: res.data.ok ? 'Alerta enviada' : 'Aviso',
                text: res.data.message,
                timer: 3500,
                showConfirmButton: false,
            });
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo enviar la alerta.' });
        } finally {
            setSendingAlert(false);
        }
    };

    /* Greeting dinámica */
    const hora   = new Date().getHours();
    const saludo = hora < 12 ? 'Buenos días' : hora < 18 ? 'Buenas tardes' : 'Buenas noches';
    const fecha  = new Date().toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const name   = typeof window !== 'undefined' ? (window.currentUser || 'Administrador') : 'Administrador';

    /* Datos del PieChart de estado de documentos */
    const docStatusData = stats ? [
        { name: 'Vigentes',    value: Math.max(0, (stats.kpis?.vigentes    ?? 0) - (stats.kpis?.por_vencer ?? 0)), fill: '#43a047' },
        { name: 'Por vencer',  value: stats.kpis?.por_vencer  ?? 0, fill: '#f57c00' },
        { name: 'Caducados',   value: stats.kpis?.caducados   ?? 0, fill: '#e53935' },
    ].filter(d => d.value > 0) : [];

    /* Datos para el RadialBarChart de cumplimiento ISO */
    const radialData = stats?.iso_data?.map(n => ({
        name:       n.norma,
        compliance: n.compliance,
        fill:       isoColor(n.norma),
    })) || [];

    return (
        <div className="gestor-app">
            {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}
            <Sidebar open={sidebarOpen} onSelect={(key, url) => {
                if (key === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (url) window.location.href = url;
            }} />

            <div className="main-content">
                <Header
                    variant="default"
                    userName={name}
                    onToggleSidebar={() => setSidebarOpen(p => !p)}
                />

                <div className="content">
                    {/* ── Hero greeting ──────────────────────────── */}
                    <div className="dash-hero">
                        <div className="dash-hero-left">
                            <div className="dash-greeting">👋 {saludo}, <strong>{name.split(' ')[0]}</strong></div>
                            <div className="dash-date">{fecha}</div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                            {/* Exportar */}
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <ExportBtn href="/reportes/pdf/cumplimiento"  icon="fa-file-pdf"   label="PDF Cumplimiento" color="#e53935" />
                                <ExportBtn href="/reportes/excel/documentos"  icon="fa-file-excel" label="Excel Docs"       color="#2e7d32" />
                                <ExportBtn href="/reportes/excel/cumplimiento" icon="fa-file-excel" label="Excel ISO"       color="#1565c0" />
                            </div>
                            <div className="dash-hero-badge">
                                <i className="fa fa-shield-alt" style={{ color: '#ff8a00', fontSize: 20, marginRight: 10 }} />
                                <div>
                                    <div style={{ fontWeight: 800, fontSize: 13, color: '#333' }}>Sistema ISO activo</div>
                                    <div style={{ fontSize: 11, color: '#888' }}>
                                        {stats?.kpis?.compliance ?? 0}% cumplimiento global
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {loading ? (
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', padding: 80, color: '#aaa', gap: 16 }}>
                            <i className="fa fa-spinner fa-spin" style={{ fontSize: 36, color: '#ff8a00' }} />
                            <span style={{ fontSize: 14, fontWeight: 600 }}>Cargando dashboard…</span>
                        </div>
                    ) : statsError ? (
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', padding: 80, color: '#aaa', gap: 12 }}>
                            <i className="fa fa-wifi" style={{ fontSize: 40, color: '#e0e0e0' }} />
                            <span style={{ fontSize: 16, fontWeight: 700, color: '#888' }}>No se pudo conectar con el servidor</span>
                            <span style={{ fontSize: 13, color: '#bbb', textAlign: 'center', maxWidth: 340 }}>
                                Verifica tu conexión a internet o contacta al administrador del sistema.
                            </span>
                            <button
                                onClick={loadStats}
                                style={{ marginTop: 8, background: '#ff8a00', color: '#fff', border: 'none', borderRadius: 10, padding: '10px 28px', fontWeight: 700, fontSize: 14, cursor: 'pointer' }}>
                                <i className="fa fa-redo" style={{ marginRight: 8 }} />Reintentar
                            </button>
                        </div>
                    ) : (
                        <>
                            {/* ── Row 1: KPI cards ───────────────────────── */}
                            <div className="kpi-grid">
                                <KPICard icon="fa-file-alt"             label="Total Documentos"    value={stats?.kpis?.documentos}     color="#ff8a00" subtitle={`${stats?.kpis?.vigentes ?? 0} vigentes`} />
                                <KPICard icon="fa-users"                label="Usuarios Activos"    value={stats?.kpis?.usuarios}       color="#42a5f5" />
                                <KPICard icon="fa-graduation-cap"       label="Capacitaciones"      value={stats?.kpis?.capacitaciones} color="#26a69a" />
                                <KPICard icon="fa-exclamation-triangle" label="Hallazgos"           value={stats?.kpis?.hallazgos}      color="#ef5350" />
                                <KPICard icon="fa-check-circle"         label="Docs Vigentes"       value={stats?.kpis?.vigentes}       color="#43a047" subtitle="Sin vencer" />
                                <KPICard icon="fa-clock"                label="Por Vencer (30d)"    value={stats?.kpis?.por_vencer}     color="#f57c00" subtitle="Próximos 30 días" />
                                <KPICard icon="fa-times-circle"         label="Docs Caducados"      value={stats?.kpis?.caducados}      color="#e53935" subtitle="Requieren revisión" />
                                <KPICard icon="fa-chart-pie"            label="Cumplimiento Global" value={stats?.kpis?.compliance}     color="#5c6bc0" subtitle="%" />
                            </div>

                            {/* ── Row 2 + 3: Gráficos — diferidos al idle callback para no bloquear primer paint ── */}
                            {!chartsReady ? (
                                <>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 20 }}>
                                        <div className="chart-skeleton" style={{ height: 260, gridColumn: '1 / 3' }} />
                                        <div className="chart-skeleton" style={{ height: 260 }} />
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '3fr 2fr', gap: 20 }}>
                                        <div className="chart-skeleton" style={{ height: 220 }} />
                                        <div className="chart-skeleton" style={{ height: 220 }} />
                                    </div>
                                </>
                            ) : (
                            <>
                            {/* ── Row 2: Gráficos principales ── */}
                            <div className="dash-charts-row" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 20 }}>

                                {/* Cumplimiento ISO — BarChart */}
                                <div className="dash-card" style={{ gridColumn: '1 / 3' }}>
                                    <div className="dash-card-header">
                                        <span><i className="fa fa-chart-bar" style={{ color: '#ff8a00', marginRight: 8 }} />Cumplimiento por Norma ISO</span>
                                        <a href="/auditoria" className="dash-card-link">Ver detalle →</a>
                                    </div>
                                    <div style={{ padding: '10px 0 16px' }}>
                                        {radialData.length === 0 ? (
                                            <div className="dash-empty">
                                                <i className="fa fa-chart-bar" style={{ fontSize: 32, marginBottom: 8 }} />
                                                <p>Crea normas ISO y vincula documentos para ver el cumplimiento</p>
                                                <a href="/auditoria" className="btn-orange" style={{ display: 'inline-flex', marginTop: 10, textDecoration: 'none', padding: '8px 16px', borderRadius: 8, fontSize: 13, fontWeight: 700, color: '#fff' }}>
                                                    Ir a Auditoría
                                                </a>
                                            </div>
                                        ) : (
                                            <ResponsiveContainer width="100%" height={220}>
                                                <BarChart
                                                    data={stats.iso_data}
                                                    margin={{ top: 10, right: 20, left: -10, bottom: 0 }}
                                                    barSize={40}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                                                    <XAxis
                                                        dataKey="norma"
                                                        tick={{ fontSize: 11, fontWeight: 700, fill: '#888' }}
                                                        axisLine={false}
                                                        tickLine={false}
                                                    />
                                                    <YAxis
                                                        domain={[0, 100]}
                                                        tick={{ fontSize: 11, fill: '#aaa' }}
                                                        axisLine={false}
                                                        tickLine={false}
                                                        unit="%"
                                                    />
                                                    <Tooltip
                                                        content={({ active, payload, label }) => {
                                                            if (!active || !payload?.length) return null;
                                                            const d = payload[0]?.payload;
                                                            return (
                                                                <div style={{
                                                                    background: '#1a1a2e', color: '#fff', borderRadius: 10,
                                                                    padding: '10px 14px', fontSize: 13, boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
                                                                }}>
                                                                    <div style={{ fontWeight: 700, marginBottom: 6, color: '#ff8a00' }}>{label}</div>
                                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                                                                        <div style={{ width: 8, height: 8, borderRadius: '50%', background: '#43a047' }} />
                                                                        <span style={{ color: '#aaa' }}>Cumplimiento:</span>
                                                                        <span style={{ fontWeight: 800, color: '#fff' }}>{d?.compliance ?? 0}%</span>
                                                                    </div>
                                                                    <div style={{ fontSize: 11, color: '#aaa', marginTop: 4, borderTop: '1px solid #333', paddingTop: 4 }}>
                                                                        {d?.fuente === 'evaluacion' ? (<>
                                                                            <div>✅ Conformes: <b style={{ color: '#43a047' }}>{d?.conformes ?? 0}</b></div>
                                                                            <div>❌ No conformes: <b style={{ color: '#e53935' }}>{d?.no_conformes ?? 0}</b></div>
                                                                            <div>⚠️ Observaciones: <b style={{ color: '#f57c00' }}>{d?.observaciones ?? 0}</b></div>
                                                                            <div>➖ N/A: <b style={{ color: '#90a4ae' }}>{d?.na ?? 0}</b></div>
                                                                            <div>📋 Total cláusulas: <b style={{ color: '#fff' }}>{d?.total_cl ?? 0}</b></div>
                                                                            <div style={{ marginTop: 4, color: '#5c6bc0', fontStyle: 'italic' }}>Basado en evaluación de cláusulas</div>
                                                                        </>) : (<>
                                                                            <div>✅ Vigentes: <b style={{ color: '#43a047' }}>{d?.vigente ?? 0}</b></div>
                                                                            <div>⚠️ Por vencer: <b style={{ color: '#f57c00' }}>{d?.por_vencer ?? 0}</b></div>
                                                                            <div>❌ Caducados: <b style={{ color: '#e53935' }}>{d?.caducado ?? 0}</b></div>
                                                                            <div>📄 Total docs: <b style={{ color: '#fff' }}>{d?.total ?? 0}</b></div>
                                                                            <div style={{ marginTop: 4, color: '#78909c', fontStyle: 'italic' }}>Basado en documentos vigentes</div>
                                                                        </>)}
                                                                    </div>
                                                                </div>
                                                            );
                                                        }}
                                                    />
                                                    <Bar dataKey="compliance" name="Cumplimiento %" radius={[8, 8, 0, 0]}>
                                                        {stats.iso_data.map((entry, index) => (
                                                            <Cell key={index} fill={isoColor(entry.norma)} />
                                                        ))}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        )}
                                    </div>
                                </div>

                                {/* Estado de documentos — PieChart */}
                                <div className="dash-card">
                                    <div className="dash-card-header">
                                        <span><i className="fa fa-chart-pie" style={{ color: '#ff8a00', marginRight: 8 }} />Estado de Documentos</span>
                                    </div>
                                    <div style={{ padding: '10px 0 0' }}>
                                        {docStatusData.length === 0 ? (
                                            <div className="dash-empty">
                                                <i className="fa fa-file-alt" style={{ fontSize: 28, marginBottom: 8 }} />
                                                <p>Sin documentos cargados</p>
                                            </div>
                                        ) : (
                                            <>
                                                <ResponsiveContainer width="100%" height={170}>
                                                    <PieChart>
                                                        <Pie
                                                            data={docStatusData}
                                                            cx="50%" cy="50%"
                                                            innerRadius={45} outerRadius={70}
                                                            paddingAngle={4}
                                                            dataKey="value"
                                                            strokeWidth={0}
                                                        >
                                                            {docStatusData.map((e, i) => (
                                                                <Cell key={i} fill={e.fill} />
                                                            ))}
                                                        </Pie>
                                                        <Tooltip
                                                            content={({ active, payload }) => {
                                                                if (!active || !payload?.length) return null;
                                                                return (
                                                                    <div style={{ background: '#1a1a2e', color: '#fff', borderRadius: 8, padding: '8px 12px', fontSize: 12 }}>
                                                                        <span style={{ color: payload[0].payload.fill, fontWeight: 800 }}>●</span>
                                                                        {' '}{payload[0].name}: <strong>{payload[0].value}</strong>
                                                                    </div>
                                                                );
                                                            }}
                                                        />
                                                    </PieChart>
                                                </ResponsiveContainer>
                                                {/* Leyenda */}
                                                <div style={{ padding: '0 16px 16px', display: 'flex', flexDirection: 'column', gap: 6 }}>
                                                    {docStatusData.map((d, i) => (
                                                        <div key={i} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: 12 }}>
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                                <div style={{ width: 10, height: 10, borderRadius: '50%', background: d.fill }} />
                                                                <span style={{ color: '#555', fontWeight: 600 }}>{d.name}</span>
                                                            </div>
                                                            <span style={{ fontWeight: 800, color: d.fill }}>{d.value}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* ── Row 3: Tendencia + Docs por vencer ─────── */}
                            <div style={{ display: 'grid', gridTemplateColumns: '3fr 2fr', gap: 20 }}>


                                {/* Tendencia de actividad — AreaChart */}
                                <div className="dash-card">
                                    <div className="dash-card-header">
                                        <span><i className="fa fa-chart-area" style={{ color: '#ff8a00', marginRight: 8 }} />Actividad últimos 7 días</span>
                                    </div>
                                    <div style={{ padding: '10px 0 16px' }}>
                                        <ResponsiveContainer width="100%" height={180}>
                                            <AreaChart
                                                data={stats?.activity_trend || []}
                                                margin={{ top: 5, right: 20, left: -20, bottom: 0 }}
                                            >
                                                <defs>
                                                    <linearGradient id="actGrad" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="5%"  stopColor="#ff8a00" stopOpacity={0.25} />
                                                        <stop offset="95%" stopColor="#ff8a00" stopOpacity={0} />
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                                                <XAxis dataKey="dia" tick={{ fontSize: 11, fontWeight: 700, fill: '#aaa' }} axisLine={false} tickLine={false} />
                                                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#aaa' }} axisLine={false} tickLine={false} />
                                                <Tooltip content={<CustomTooltip />} />
                                                <Area
                                                    type="monotone" dataKey="count" name="Eventos"
                                                    stroke="#ff8a00" strokeWidth={2.5}
                                                    fill="url(#actGrad)"
                                                    dot={{ fill: '#ff8a00', r: 4 }}
                                                    activeDot={{ r: 6, fill: '#ff8a00' }}
                                                />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>

                                {/* Documentos por vencer */}
                                <div className="dash-card">
                                    <div className="dash-card-header">
                                        <span>
                                            <i className="fa fa-clock" style={{ color: '#f57c00', marginRight: 8 }} />
                                            Por vencer (30d)
                                        </span>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                            {(stats?.docs_expiring?.length ?? 0) > 0 && (
                                                <span style={{ background: '#fff8e1', color: '#f57c00', borderRadius: 20, padding: '2px 8px', fontSize: 11, fontWeight: 800 }}>
                                                    {stats.docs_expiring.length}
                                                </span>
                                            )}
                                            {currentRole === 'Administrador' && (
                                                <button
                                                    onClick={handleEnviarAlerta}
                                                    disabled={sendingAlert}
                                                    title="Enviar alerta por email a administradores"
                                                    style={{
                                                        display: 'inline-flex', alignItems: 'center', gap: 5,
                                                        padding: '4px 10px', borderRadius: 8, cursor: sendingAlert ? 'wait' : 'pointer',
                                                        border: '1.5px solid #f57c00', background: '#fff8e1',
                                                        color: '#f57c00', fontSize: 11, fontWeight: 700,
                                                    }}
                                                >
                                                    <i className={`fa ${sendingAlert ? 'fa-spinner fa-spin' : 'fa-envelope'}`} />
                                                    {sendingAlert ? 'Enviando…' : 'Enviar alerta'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <div style={{ padding: '12px 14px', maxHeight: 220, overflowY: 'auto' }}>
                                        {!(stats?.docs_expiring?.length) ? (
                                            <div className="dash-empty" style={{ padding: 24 }}>
                                                <i className="fa fa-check-circle" style={{ color: '#43a047', fontSize: 28, marginBottom: 8 }} />
                                                <p>¡Sin documentos por vencer!</p>
                                            </div>
                                        ) : (
                                            stats.docs_expiring.map(d => (
                                                <ExpiringDocItem key={d.id} doc={d} />
                                            ))
                                        )}
                                    </div>
                                </div>
                            </div>
                            </> /* fin chartsReady ternario */
                            )} {/* fin !chartsReady ? ... : ... */}

                            {/* ── Row 4: Actividad reciente + docs recientes */}
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 20 }}>

                                {/* Actividad reciente (timeline) */}
                                <div className="dash-card">
                                    <div className="dash-card-header">
                                        <span><i className="fa fa-history" style={{ color: '#ff8a00', marginRight: 8 }} />Actividad Reciente</span>
                                        <a href="/auditoria" className="dash-card-link">Ver todo →</a>
                                    </div>
                                    <div style={{ padding: '12px 16px' }}>
                                        {!(stats?.logs_recientes?.length) ? (
                                            <div className="dash-empty">
                                                <i className="fa fa-stream" style={{ fontSize: 28, marginBottom: 8 }} />
                                                <p>Sin actividad registrada</p>
                                            </div>
                                        ) : (
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                                {stats.logs_recientes.map((log, i) => {
                                                    const cfg = MODULE_CFG[log.modulo] || { color: '#78909c', bg: '#eceff1', icon: 'fa-circle' };
                                                    return (
                                                        <div key={log.id} style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                                            {/* Línea de tiempo */}
                                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                                                <div style={{
                                                                    width: 34, height: 34, borderRadius: 10, flexShrink: 0,
                                                                    background: cfg.bg,
                                                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                                    color: cfg.color, fontSize: 13,
                                                                }}>
                                                                    <i className={`fa ${cfg.icon}`} />
                                                                </div>
                                                                {i < stats.logs_recientes.length - 1 && (
                                                                    <div style={{ width: 1, flex: 1, background: '#f0f0f0', minHeight: 12 }} />
                                                                )}
                                                            </div>
                                                            <div style={{ flex: 1, paddingBottom: 12 }}>
                                                                <div style={{ fontSize: 13, fontWeight: 600, color: '#333', lineHeight: 1.4 }}>
                                                                    {log.descripcion}
                                                                </div>
                                                                <div style={{ fontSize: 11, color: '#aaa', marginTop: 2 }}>
                                                                    {log.nombre_usuario?.trim() || 'Sistema'} ·{' '}
                                                                    {new Date(log.created_at).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' })}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Documentos recientes */}
                                <div className="dash-card">
                                    <div className="dash-card-header">
                                        <span><i className="fa fa-file-alt" style={{ color: '#ff8a00', marginRight: 8 }} />Documentos Recientes</span>
                                        <a href="/gestor_documentacion" className="dash-card-link">Ver todos →</a>
                                    </div>
                                    {!(stats?.docs_recientes?.length) ? (
                                        <div className="dash-empty" style={{ padding: 40 }}>
                                            <i className="fa fa-inbox" style={{ fontSize: 36, marginBottom: 10 }} />
                                            <p>No hay documentos registrados</p>
                                        </div>
                                    ) : (
                                        <div className="table-wrapper">
                                            <table className="user-table">
                                                <thead>
                                                    <tr>
                                                        <th>Documento</th>
                                                        <th style={{ textAlign: 'center', width: 70 }}>Ver.</th>
                                                        <th style={{ textAlign: 'center', width: 110 }}>Fecha</th>
                                                        <th style={{ textAlign: 'center', width: 100 }}>Vencimiento</th>
                                                        <th style={{ textAlign: 'center', width: 60 }}></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {stats.docs_recientes.map(doc => {
                                                        const hoy     = new Date();
                                                        const vence   = doc.vence ? new Date(doc.vence) : null;
                                                        const dias    = vence ? Math.floor((vence - hoy) / 86400000) : null;
                                                        const expired = dias !== null && dias < 0;
                                                        const soon    = dias !== null && dias >= 0 && dias <= 30;
                                                        return (
                                                            <tr key={doc.id}>
                                                                <td>
                                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                                        <i className="fa fa-file-pdf" style={{ color: '#e53935', fontSize: 15 }} />
                                                                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: 200 }}>
                                                                            {doc.nombre}
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td style={{ textAlign: 'center' }}>
                                                                    <span style={{ background: '#fff3e0', color: '#e65100', padding: '2px 10px', borderRadius: 20, fontSize: 11, fontWeight: 800 }}>
                                                                        v{doc.version}
                                                                    </span>
                                                                </td>
                                                                <td style={{ textAlign: 'center', fontSize: 12, color: '#888' }}>
                                                                    {doc.fecha ? new Date(doc.fecha).toLocaleDateString('es-CO', { dateStyle: 'short' }) : '—'}
                                                                </td>
                                                                <td style={{ textAlign: 'center' }}>
                                                                    {vence ? (
                                                                        <span style={{
                                                                            background: expired ? '#fdecea' : soon ? '#fff8e1' : '#e8f5e9',
                                                                            color: expired ? '#e53935' : soon ? '#f57c00' : '#43a047',
                                                                            padding: '2px 9px', borderRadius: 20, fontSize: 11, fontWeight: 700,
                                                                        }}>
                                                                            {expired ? 'Caducado' : `${dias}d`}
                                                                        </span>
                                                                    ) : <span style={{ color: '#ccc', fontSize: 12 }}>—</span>}
                                                                </td>
                                                                <td style={{ textAlign: 'center' }}>
                                                                    {doc.ruta && (
                                                                        <a href={doc.ruta} target="_blank" rel="noreferrer" style={{ color: '#ff8a00', fontSize: 16 }}>
                                                                            <i className="fa fa-external-link-alt" />
                                                                        </a>
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
