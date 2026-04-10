import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';

const MODULOS = [
    { value: '',               label: 'Todos los módulos' },
    { value: 'auth',           label: 'Autenticación' },
    { value: 'documentos',     label: 'Documentos' },
    { value: 'usuarios',       label: 'Usuarios' },
    { value: 'capacitaciones', label: 'Capacitaciones' },
    { value: 'auditorias',     label: 'Auditorías' },
    { value: 'hallazgos',      label: 'Hallazgos' },
    { value: 'permisos',       label: 'Permisos' },
    { value: 'reportes',       label: 'Reportes' },
];

const moduloColor = {
    auth: { bg: '#e8eaf6', text: '#3949ab' },
    documentos: { bg: '#e0f2f1', text: '#00796b' },
    usuarios: { bg: '#fce4ec', text: '#c62828' },
    capacitaciones: { bg: '#e3f2fd', text: '#1565c0' },
};

const accionIcon = {
    login: 'fa-sign-in-alt',
    login_fallido: 'fa-times-circle',
    upload_doc: 'fa-upload',
    delete_doc: 'fa-trash',
    nueva_version: 'fa-code-branch',
    crear_usuario: 'fa-user-plus',
    editar_usuario: 'fa-user-edit',
    eliminar_usuario: 'fa-user-minus',
};

const FILTROS_VACÍOS = { modulo: '', desde: '', hasta: '', q: '' };

export default function ActivityLogs() {
    const [logs,    setLogs]    = useState([]);
    const [loading, setLoading] = useState(false);
    const [error,   setError]   = useState(false);
    const [filters, setFilters] = useState(FILTROS_VACÍOS);

    const hayFiltros = filters.modulo || filters.desde || filters.hasta || filters.q;

    const fetchLogs = useCallback(() => {
        setLoading(true);
        setError(false);
        const params = {};
        if (filters.modulo) params.modulo = filters.modulo;
        if (filters.desde)  params.desde  = filters.desde;
        if (filters.hasta)  params.hasta  = filters.hasta;
        if (filters.q)      params.q      = filters.q;

        axios.get('/api/activity-logs', { params })
            .then(r => setLogs(r.data?.data ?? r.data ?? []))
            .catch(e => {
                console.error(e);
                setError(true);
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo cargar el historial de actividad. Verifica tu conexión e intenta de nuevo.', timer: 4000, showConfirmButton: false });
            })
            .finally(() => setLoading(false));
    }, [filters]);

    useEffect(() => { fetchLogs(); }, [fetchLogs]);

    const set = (k, v) => setFilters(f => ({ ...f, [k]: v }));

    return (
        <div>
            {/* ── Filtros ─────────────────────────────────────────── */}
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 18, alignItems: 'center' }}>
                <div style={{ position: 'relative', flex: '1 1 200px', minWidth: 180 }}>
                    <i className="fa fa-search" style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: '#bbb', fontSize: 13 }} />
                    <input
                        type="text"
                        placeholder="Buscar usuario, acción..."
                        value={filters.q}
                        onChange={e => set('q', e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && fetchLogs()}
                        style={{ border: '1px solid #e0e0e0', borderRadius: 8, padding: '8px 14px 8px 34px', fontSize: 13, width: '100%' }}
                    />
                </div>
                <select
                    value={filters.modulo}
                    onChange={e => set('modulo', e.target.value)}
                    style={{ border: '1px solid #e0e0e0', borderRadius: 8, padding: '8px 12px', fontSize: 13, minWidth: 160 }}
                >
                    {MODULOS.map(m => (
                        <option key={m.value} value={m.value}>{m.label}</option>
                    ))}
                </select>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <label style={{ fontSize: 12, color: '#888', whiteSpace: 'nowrap' }}>Desde</label>
                    <input type="date" value={filters.desde} onChange={e => set('desde', e.target.value)}
                        style={{ border: '1px solid #e0e0e0', borderRadius: 8, padding: '8px 10px', fontSize: 13 }} />
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <label style={{ fontSize: 12, color: '#888', whiteSpace: 'nowrap' }}>Hasta</label>
                    <input type="date" value={filters.hasta} onChange={e => set('hasta', e.target.value)}
                        style={{ border: '1px solid #e0e0e0', borderRadius: 8, padding: '8px 10px', fontSize: 13 }} />
                </div>
                <button onClick={fetchLogs}
                    style={{ background: 'linear-gradient(135deg,#ff8a00,#e67300)', color: '#fff', border: 'none', borderRadius: 8, padding: '8px 18px', fontWeight: 700, fontSize: 13, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                    <i className="fa fa-search" style={{ marginRight: 6 }}></i>Filtrar
                </button>
                {hayFiltros && (
                    <button
                        onClick={() => setFilters(FILTROS_VACÍOS)}
                        title="Limpiar filtros"
                        style={{ background: '#f5f5f5', color: '#888', border: '1px solid #e0e0e0', borderRadius: 8, padding: '8px 14px', fontWeight: 600, fontSize: 13, cursor: 'pointer', whiteSpace: 'nowrap' }}>
                        <i className="fa fa-times" style={{ marginRight: 5 }}></i>Limpiar
                    </button>
                )}
            </div>

            {/* ── Contenido ───────────────────────────────────────── */}
            {loading ? (
                <div style={{ textAlign: 'center', padding: 60, color: '#aaa' }}>
                    <i className="fa fa-spinner fa-spin" style={{ fontSize: 30, color: '#ff8a00' }}></i>
                    <p style={{ marginTop: 12, fontSize: 14, fontWeight: 500 }}>Cargando historial…</p>
                </div>
            ) : error ? (
                <div style={{ textAlign: 'center', padding: 60, color: '#e53935' }}>
                    <i className="fa fa-exclamation-circle" style={{ fontSize: 36, marginBottom: 12 }}></i>
                    <p style={{ fontSize: 14, fontWeight: 600 }}>No se pudo cargar el historial</p>
                    <button onClick={fetchLogs} style={{ marginTop: 12, background: '#e53935', color: '#fff', border: 'none', borderRadius: 8, padding: '8px 20px', cursor: 'pointer', fontWeight: 700, fontSize: 13 }}>
                        <i className="fa fa-redo" style={{ marginRight: 6 }}></i>Reintentar
                    </button>
                </div>
            ) : logs.length === 0 ? (
                <div style={{ textAlign: 'center', padding: 60, color: '#bbb' }}>
                    <i className="fa fa-clipboard-list" style={{ fontSize: 42, marginBottom: 12, color: '#ddd' }}></i>
                    <p style={{ fontSize: 14, fontWeight: 600, color: '#999' }}>
                        {hayFiltros ? 'No hay actividad que coincida con los filtros aplicados' : 'No hay actividad registrada aún'}
                    </p>
                    {hayFiltros && (
                        <button onClick={() => setFilters(FILTROS_VACÍOS)} style={{ marginTop: 10, background: '#ff8a00', color: '#fff', border: 'none', borderRadius: 8, padding: '7px 18px', cursor: 'pointer', fontWeight: 700, fontSize: 13 }}>
                            <i className="fa fa-times" style={{ marginRight: 5 }}></i>Quitar filtros
                        </button>
                    )}
                </div>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                        <thead>
                            <tr style={{ background: '#fff3e0' }}>
                                <th style={thStyle}>Fecha</th>
                                <th style={thStyle}>Usuario</th>
                                <th style={thStyle}>Módulo</th>
                                <th style={thStyle}>Acción</th>
                                <th style={{ ...thStyle, textAlign: 'left' }}>Descripción</th>
                                <th style={thStyle}>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.map((log, i) => {
                                const mc = moduloColor[log.modulo] || { bg: '#f5f5f5', text: '#555' };
                                return (
                                    <tr key={log.id} style={{ borderBottom: '1px solid #f4f4f4', background: i % 2 === 0 ? '#fff' : '#fafafa' }}>
                                        <td style={{ ...tdStyle, whiteSpace: 'nowrap', color: '#888' }}>
                                            {log.created_at ? new Date(log.created_at).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }) : '—'}
                                        </td>
                                        <td style={{ ...tdStyle, fontWeight: 600, color: '#333' }}>{log.nombre_usuario || '—'}</td>
                                        <td style={tdStyle}>
                                            <span style={{ background: mc.bg, color: mc.text, padding: '3px 10px', borderRadius: 99, fontWeight: 600, fontSize: 11 }}>
                                                {log.modulo}
                                            </span>
                                        </td>
                                        <td style={{ ...tdStyle, textAlign: 'center' }}>
                                            <i className={`fa ${accionIcon[log.accion] || 'fa-circle'}`} style={{ color: mc.text, fontSize: 15 }} title={log.accion}></i>
                                        </td>
                                        <td style={{ ...tdStyle, color: '#555', maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {log.descripcion || '—'}
                                        </td>
                                        <td style={{ ...tdStyle, color: '#aaa', fontFamily: 'monospace', fontSize: 11 }}>{log.ip || '—'}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

const thStyle = {
    padding: '10px 14px',
    textAlign: 'center',
    fontSize: 12,
    fontWeight: 700,
    color: '#e65100',
};

const tdStyle = {
    padding: '10px 14px',
    textAlign: 'center',
    fontSize: 13,
};
