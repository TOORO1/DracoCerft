import React, { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import Sidebar from './Sidebar';
import Header from './Header';
import ActivityLogs from './ActivityLogs';

/* ══════════════════════════════════════════════════════════════════
 |  SweetAlert con z-index alto (para usarse dentro de modales)
 ══════════════════════════════════════════════════════════════════ */
const Alert = Swal.mixin({
    didOpen: () => {
        const container = document.querySelector('.swal2-container');
        if (container) container.style.zIndex = '100000';
    },
});

/* ══════════════════════════════════════════════════════════════════
 |  Colores por norma ISO (consistente con el resto de la app)
 ══════════════════════════════════════════════════════════════════ */
const ISO_COLORS = {
    '9001':  { bg: '#fff3e0', accent: '#ff8a00', icon: 'fa-award',      label: 'ISO 9001' },
    '14001': { bg: '#e0f2f1', accent: '#26a69a', icon: 'fa-leaf',       label: 'ISO 14001' },
    '27001': { bg: '#ede7f6', accent: '#5c6bc0', icon: 'fa-shield-alt', label: 'ISO 27001' },
    default: { bg: '#eceff1', accent: '#78909c', icon: 'fa-file-alt',   label: 'Norma' },
};

function getISOColor(codigo) {
    if (!codigo) return ISO_COLORS.default;
    if (codigo.includes('9001'))  return ISO_COLORS['9001'];
    if (codigo.includes('14001')) return ISO_COLORS['14001'];
    if (codigo.includes('27001')) return ISO_COLORS['27001'];
    return ISO_COLORS.default;
}

/* ══════════════════════════════════════════════════════════════════
 |  Componente CircularProgress (animado, sin dependencia)
 ══════════════════════════════════════════════════════════════════ */
const CircularProgress = ({ value = 0, label, color = '#ff8a00', size = 110 }) => {
    const radius = (size / 2) - 10;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (Math.min(Math.max(value, 0), 100) / 100) * circumference;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
            <div style={{ position: 'relative', width: size, height: size }}>
                <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
                    <circle cx={size / 2} cy={size / 2} r={radius}
                        stroke="#eee" strokeWidth="9" fill="transparent" />
                    <circle cx={size / 2} cy={size / 2} r={radius}
                        stroke={color} strokeWidth="9" fill="transparent"
                        strokeDasharray={circumference}
                        strokeDashoffset={offset}
                        strokeLinecap="round"
                        style={{ transition: 'stroke-dashoffset 0.8s ease' }}
                    />
                </svg>
                <div style={{
                    position: 'absolute', inset: 0,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontWeight: 800, fontSize: size * 0.18, color: '#333',
                }}>
                    {value}%
                </div>
            </div>
            <span style={{ fontSize: 13, fontWeight: 700, color: '#555', textAlign: 'center' }}>{label}</span>
        </div>
    );
};

/* ══════════════════════════════════════════════════════════════════
 |  Componente Badge de prioridad
 ══════════════════════════════════════════════════════════════════ */
const PriorityBadge = ({ prioridad }) => {
    const cfg = {
        alta:  { bg: '#fdecea', color: '#d32f2f', label: 'Alta' },
        media: { bg: '#fff8e1', color: '#f57c00', label: 'Media' },
        baja:  { bg: '#e8f5e9', color: '#388e3c', label: 'Baja' },
    }[prioridad] || { bg: '#eceff1', color: '#607d8b', label: prioridad };

    return (
        <span style={{
            display: 'inline-block', padding: '2px 10px', borderRadius: 20,
            fontSize: 11, fontWeight: 700, background: cfg.bg, color: cfg.color,
        }}>
            {cfg.label}
        </span>
    );
};

/* ══════════════════════════════════════════════════════════════════
 |  Modal genérico reutilizable
 ══════════════════════════════════════════════════════════════════ */
const Modal = ({ open, onClose, title, children, width = 520 }) => {
    if (!open) return null;
    return (
        <div style={{
            position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.45)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            zIndex: 9999, padding: 20,
        }} onClick={e => e.target === e.currentTarget && onClose()}>
            <div style={{
                background: '#fff', borderRadius: 12, width: '100%', maxWidth: width,
                maxHeight: '90vh', overflow: 'auto',
                boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
            }}>
                <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    padding: '18px 24px', borderBottom: '1px solid #f0f0f0',
                }}>
                    <h3 style={{ margin: 0, fontSize: 16, fontWeight: 800, color: '#333' }}>{title}</h3>
                    <button onClick={onClose} style={{
                        background: 'none', border: 'none', cursor: 'pointer',
                        width: 32, height: 32, borderRadius: '50%',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        color: '#999', fontSize: 18,
                        transition: 'background 0.2s',
                    }}
                        onMouseEnter={e => e.currentTarget.style.background = '#f5f5f5'}
                        onMouseLeave={e => e.currentTarget.style.background = 'none'}
                    >
                        <i className="fa fa-times" />
                    </button>
                </div>
                <div style={{ padding: '20px 24px' }}>{children}</div>
            </div>
        </div>
    );
};

/* ══════════════════════════════════════════════════════════════════
 |  Campo de formulario reutilizable
 ══════════════════════════════════════════════════════════════════ */
const Field = ({ label, required, children }) => (
    <div style={{ marginBottom: 16 }}>
        <label style={{ display: 'block', fontSize: 13, fontWeight: 700, color: '#555', marginBottom: 6 }}>
            {label}{required && <span style={{ color: '#e53935', marginLeft: 2 }}>*</span>}
        </label>
        {children}
    </div>
);

const inputStyle = {
    width: '100%', padding: '9px 12px', borderRadius: 8,
    border: '1.5px solid #e0e0e0', fontSize: 13, outline: 'none',
    transition: 'border-color 0.2s', fontFamily: 'inherit',
    boxSizing: 'border-box',
};

/* ══════════════════════════════════════════════════════════════════
 |  Botón de exportación inline para Auditoría
 ══════════════════════════════════════════════════════════════════ */
function AuditExportBtn({ href, icon, label, color }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            style={{
                display: 'inline-flex', alignItems: 'center', gap: 5,
                padding: '5px 12px', borderRadius: 6, textDecoration: 'none',
                background: color + '12', color, border: `1.5px solid ${color}35`,
                fontSize: 12, fontWeight: 700, transition: 'all 0.18s',
            }}
            onMouseEnter={e => {
                e.currentTarget.style.background = color;
                e.currentTarget.style.color = '#fff';
            }}
            onMouseLeave={e => {
                e.currentTarget.style.background = color + '12';
                e.currentTarget.style.color = color;
            }}
        >
            <i className={`fa ${icon}`} />
            <span>{label}</span>
        </a>
    );
}

/* ══════════════════════════════════════════════════════════════════
 |  Etiqueta de estado de cláusula ISO
 ══════════════════════════════════════════════════════════════════ */
const ESTADOS = [
    { val: 'conforme',     label: 'Conforme',      short: 'C',  color: '#43a047', bg: '#e8f5e9' },
    { val: 'no_conforme',  label: 'No Conforme',   short: 'NC', color: '#e53935', bg: '#fdecea' },
    { val: 'observacion',  label: 'Observación',   short: 'OB', color: '#f57c00', bg: '#fff8e1' },
    { val: 'na',           label: 'N/A',           short: 'NA', color: '#9e9e9e', bg: '#f5f5f5' },
];

function EstadoBadge({ estado }) {
    const cfg = ESTADOS.find(e => e.val === estado) || ESTADOS[3];
    return (
        <span style={{
            display: 'inline-block', padding: '2px 9px', borderRadius: 20,
            fontSize: 11, fontWeight: 800, background: cfg.bg, color: cfg.color,
        }}>
            {cfg.label}
        </span>
    );
}

/* ══════════════════════════════════════════════════════════════════
 |  Modal de Evaluación de Cláusulas ISO
 ══════════════════════════════════════════════════════════════════ */
function EvaluacionModal({ open, onClose, auditoria, normas }) {
    const [selectedNormaId, setSelectedNormaId] = useState('');
    const [evalData, setEvalData]               = useState(null);  // { norma, clausulas, resumen }
    const [loadingEval, setLoadingEval]         = useState(false);
    const [expandedId, setExpandedId]           = useState(null);   // clausula_codigo with open comment
    const [comentarios, setComentarios]         = useState({});      // local draft comments
    const [finalizando, setFinalizando]         = useState(false);
    const saveTimers = useRef({});

    // Reset on open/close
    useEffect(() => {
        if (!open) {
            setSelectedNormaId('');
            setEvalData(null);
            setExpandedId(null);
            setComentarios({});
        }
    }, [open]);

    const fetchEval = useCallback(async (normaId) => {
        if (!normaId || !auditoria) return;
        setLoadingEval(true);
        try {
            const res = await axios.get(`/api/auditorias/${auditoria.id}/evaluacion/${normaId}`);
            if (res.data.ok) {
                setEvalData(res.data);
                // Seed local comment drafts
                const drafts = {};
                res.data.clausulas.forEach(cl => {
                    drafts[cl.clausula_codigo] = cl.comentario || '';
                });
                setComentarios(drafts);
            }
        } catch (err) {
            console.error('fetchEval:', err);
        }
        setLoadingEval(false);
    }, [auditoria]);

    const handleNormaChange = (normaId) => {
        setSelectedNormaId(normaId);
        setEvalData(null);
        setComentarios({});
        if (normaId) fetchEval(normaId);
    };

    // Silent reload — updates evalData from server without showing the loading spinner.
    // Used after each estado save to confirm the DB-persisted state.
    const reloadEval = useCallback(async (normaId) => {
        if (!normaId || !auditoria) return;
        try {
            const res = await axios.get(`/api/auditorias/${auditoria.id}/evaluacion/${normaId}`);
            if (res.data.ok) setEvalData(res.data);
        } catch (err) {
            console.error('[DracoCert] reloadEval error:', err.response?.data || err.message);
        }
    }, [auditoria]);

    const saveClausula = async (cl, estado, comentario, normaId) => {
        if (!auditoria || !normaId) {
            console.warn('[DracoCert] saveClausula abortado: falta auditoria o normaId', { auditoriaId: auditoria?.id, normaId });
            return false;
        }
        try {
            const payload = {
                norma_id:        parseInt(normaId, 10),
                clausula_codigo: cl.clausula_codigo,
                clausula_titulo: cl.clausula_titulo,
                estado,
                comentario,
            };
            await axios.post(`/api/auditorias/${auditoria.id}/evaluacion`, payload);
            return true;
        } catch (err) {
            console.error('[DracoCert] saveClausula error:', err.response?.data || err.message);
            Alert.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: err.response?.data?.message || 'No se pudo guardar la evaluación.',
                timer: 3000,
                showConfirmButton: false,
            });
            return false;
        }
    };

    const handleEstadoChange = async (cl, nuevoEstado) => {
        const normaId = selectedNormaId; // capturar antes de cualquier await
        if (!normaId) return;

        // Actualización optimista — feedback inmediato en la UI
        setEvalData(prev => {
            if (!prev) return prev;
            const updated = prev.clausulas.map(c =>
                c.clausula_codigo === cl.clausula_codigo ? { ...c, estado: nuevoEstado } : c
            );
            const total        = prev.resumen.total;
            const conformesNum = updated.filter(c => c.estado === 'conforme').length;
            const noConformes  = updated.filter(c => c.estado === 'no_conforme').length;
            const observ       = updated.filter(c => c.estado === 'observacion').length;
            const evaluables   = conformesNum + noConformes + observ;
            return {
                ...prev,
                clausulas: updated,
                resumen: {
                    ...prev.resumen,
                    conformes:     conformesNum,
                    no_conformes:  noConformes,
                    observaciones: observ,
                    na:            total - evaluables,
                    // pct = conformes / total (incluye N/A en denominador → progreso real)
                    pct:           total > 0 ? Math.round((conformesNum / total) * 100) : 0,
                },
            };
        });

        // Guardar en BD y luego confirmar el estado real desde el servidor
        await saveClausula(cl, nuevoEstado, comentarios[cl.clausula_codigo] || '', normaId);
        reloadEval(normaId);
    };

    const handleComentarioChange = (codigo, valor) => {
        setComentarios(prev => ({ ...prev, [codigo]: valor }));
        // Debounce save — capture normaId now so the timeout always has the right value
        const normaIdSnapshot = selectedNormaId;
        if (saveTimers.current[codigo]) clearTimeout(saveTimers.current[codigo]);
        saveTimers.current[codigo] = setTimeout(() => {
            const cl = evalData?.clausulas.find(c => c.clausula_codigo === codigo);
            if (cl) saveClausula(cl, cl.estado, valor, normaIdSnapshot);
        }, 800);
    };

    if (!open) return null;

    const resumen = evalData?.resumen;
    const color = resumen
        ? (resumen.pct >= 80 ? '#43a047' : resumen.pct >= 50 ? '#f57c00' : '#e53935')
        : '#ccc';

    return (
        <div style={{
            position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.55)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            zIndex: 10000, padding: '16px',
        }} onClick={e => e.target === e.currentTarget && onClose()}>
            <div style={{
                background: '#fff', borderRadius: 14, width: '100%', maxWidth: 920,
                maxHeight: '92vh', overflow: 'hidden', display: 'flex', flexDirection: 'column',
                boxShadow: '0 24px 80px rgba(0,0,0,0.25)',
            }}>
                {/* Header */}
                <div style={{
                    padding: '18px 24px', borderBottom: '1px solid #f0f0f0',
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    background: 'linear-gradient(135deg, #ff8a00 0%, #e65100 100%)',
                    flexShrink: 0,
                }}>
                    <div>
                        <div style={{ color: '#fff', fontWeight: 800, fontSize: 16 }}>
                            <i className="fa fa-clipboard-check" style={{ marginRight: 8 }} />
                            Evaluación de Cláusulas ISO
                        </div>
                        {auditoria && (
                            <div style={{ color: 'rgba(255,255,255,0.85)', fontSize: 12, marginTop: 2 }}>
                                {auditoria.titulo}
                                {auditoria.fecha && ` · ${new Date(auditoria.fecha).toLocaleDateString('es-CO')}`}
                            </div>
                        )}
                    </div>
                    <button onClick={onClose} style={{
                        background: 'rgba(255,255,255,0.2)', border: 'none', color: '#fff',
                        width: 34, height: 34, borderRadius: '50%', cursor: 'pointer',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16,
                    }}>
                        <i className="fa fa-times" />
                    </button>
                </div>

                {/* Norma selector + stats */}
                <div style={{
                    padding: '14px 24px', borderBottom: '1px solid #f0f0f0',
                    display: 'flex', alignItems: 'center', gap: 20, flexWrap: 'wrap',
                    background: '#fafafa', flexShrink: 0,
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flex: '0 0 auto' }}>
                        <label style={{ fontSize: 13, fontWeight: 700, color: '#555', whiteSpace: 'nowrap' }}>
                            <i className="fa fa-balance-scale" style={{ marginRight: 6, color: '#ff8a00' }} />
                            Norma ISO:
                        </label>
                        <select
                            value={selectedNormaId}
                            onChange={e => handleNormaChange(e.target.value)}
                            style={{
                                padding: '7px 12px', borderRadius: 8, border: '1.5px solid #e0e0e0',
                                fontSize: 13, fontWeight: 700, cursor: 'pointer', minWidth: 180,
                                outline: 'none',
                            }}
                        >
                            <option value="">— Seleccionar norma —</option>
                            {normas.map(n => (
                                <option key={n.idNorma} value={n.idNorma}>{n.Codigo_norma}</option>
                            ))}
                        </select>
                    </div>

                    {resumen && (
                        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
                            <div style={{
                                background: '#f5f5f5', borderRadius: 8, padding: '6px 14px',
                                display: 'flex', alignItems: 'center', gap: 6,
                            }}>
                                <div style={{
                                    width: 110, background: '#eee', borderRadius: 6, height: 8, overflow: 'hidden',
                                }}>
                                    <div style={{
                                        width: `${resumen.pct}%`, height: '100%',
                                        background: color, borderRadius: 6,
                                        transition: 'width 0.5s ease',
                                    }} />
                                </div>
                                <span style={{ fontWeight: 900, fontSize: 15, color }}>
                                    {resumen.pct}%
                                </span>
                                <span style={{ fontSize: 11, color: '#888' }}>cumplimiento</span>
                            </div>
                            {[
                                { label: 'Conformes',    val: resumen.conformes,     color: '#43a047', bg: '#e8f5e9' },
                                { label: 'No Conformes', val: resumen.no_conformes,  color: '#e53935', bg: '#fdecea' },
                                { label: 'Observaciones',val: resumen.observaciones, color: '#f57c00', bg: '#fff8e1' },
                                { label: 'N/A',          val: resumen.na,            color: '#9e9e9e', bg: '#f5f5f5' },
                            ].map(s => (
                                <div key={s.label} style={{
                                    background: s.bg, color: s.color,
                                    borderRadius: 20, padding: '3px 12px',
                                    fontSize: 12, fontWeight: 700, whiteSpace: 'nowrap',
                                }}>
                                    {s.val} {s.label}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Clauses list */}
                <div style={{ overflowY: 'auto', flex: 1, padding: '8px 24px 20px' }}>
                    {!selectedNormaId && (
                        <div style={{ padding: 40, textAlign: 'center', color: '#bbb' }}>
                            <i className="fa fa-hand-point-up" style={{ fontSize: 36, marginBottom: 12 }} />
                            <p style={{ fontSize: 14 }}>Selecciona una norma ISO para comenzar la evaluación de cláusulas.</p>
                        </div>
                    )}

                    {selectedNormaId && loadingEval && (
                        <div style={{ padding: 40, textAlign: 'center', color: '#ff8a00' }}>
                            <i className="fa fa-spinner fa-spin" style={{ fontSize: 28 }} />
                        </div>
                    )}

                    {selectedNormaId && !loadingEval && evalData && evalData.clausulas.length === 0 && (
                        <div style={{ padding: 40, textAlign: 'center' }}>
                            <i className="fa fa-info-circle" style={{ fontSize: 36, marginBottom: 12, color: '#78909c' }} />
                            <p style={{ color: '#555', fontWeight: 600, marginBottom: 6 }}>
                                Esta norma no tiene cláusulas precargadas.
                            </p>
                            <p style={{ color: '#999', fontSize: 13 }}>
                                Las cláusulas se cargan automáticamente para normas que contengan
                                <strong> ISO 9001</strong>, <strong>ISO 14001</strong> o <strong>ISO 27001</strong> en su código.
                            </p>
                            <p style={{ color: '#bbb', fontSize: 12, marginTop: 8 }}>
                                Si deseas evaluar esta norma, asegúrate de que su código incluya el número de estándar correspondiente.
                            </p>
                        </div>
                    )}

                    {selectedNormaId && !loadingEval && evalData && evalData.clausulas.length > 0 && (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, paddingTop: 10 }}>
                            {evalData.clausulas.map((cl, idx) => {
                                const estadoCfg = ESTADOS.find(e => e.val === cl.estado) || ESTADOS[3];
                                const isExpanded = expandedId === cl.clausula_codigo;

                                return (
                                    <div key={cl.clausula_codigo} style={{
                                        border: `1.5px solid ${cl.estado !== 'na' ? estadoCfg.color + '55' : '#f0f0f0'}`,
                                        borderRadius: 10, overflow: 'hidden',
                                        background: cl.estado !== 'na' ? estadoCfg.bg + '88' : '#fff',
                                        transition: 'border-color 0.2s, background 0.2s',
                                    }}>
                                        <div style={{
                                            display: 'flex', alignItems: 'center', gap: 12, padding: '10px 14px',
                                        }}>
                                            {/* Clause number */}
                                            <div style={{
                                                width: 44, flexShrink: 0, textAlign: 'center',
                                                fontWeight: 900, fontSize: 13, color: '#ff8a00',
                                                background: '#fff3e0', borderRadius: 6, padding: '4px 0',
                                            }}>
                                                {cl.clausula_codigo}
                                            </div>

                                            {/* Clause title */}
                                            <div style={{ flex: 1, fontSize: 13, color: '#333', fontWeight: 600 }}>
                                                {cl.clausula_titulo}
                                            </div>

                                            {/* Estado buttons */}
                                            <div style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
                                                {ESTADOS.map(est => (
                                                    <button
                                                        key={est.val}
                                                        title={est.label}
                                                        onClick={() => handleEstadoChange(cl, est.val)}
                                                        style={{
                                                            padding: '5px 10px', borderRadius: 6, cursor: 'pointer',
                                                            border: cl.estado === est.val
                                                                ? `2px solid ${est.color}`
                                                                : '2px solid #e0e0e0',
                                                            background: cl.estado === est.val ? est.bg : '#fff',
                                                            color: cl.estado === est.val ? est.color : '#bbb',
                                                            fontWeight: 800, fontSize: 11,
                                                            transition: 'all 0.15s',
                                                        }}
                                                    >
                                                        {est.short}
                                                    </button>
                                                ))}
                                            </div>

                                            {/* Toggle comment */}
                                            <button
                                                title="Añadir comentario"
                                                onClick={() => setExpandedId(isExpanded ? null : cl.clausula_codigo)}
                                                style={{
                                                    width: 30, height: 30, borderRadius: '50%', border: 'none',
                                                    cursor: 'pointer', flexShrink: 0,
                                                    background: isExpanded || comentarios[cl.clausula_codigo]
                                                        ? '#e3f2fd' : '#f5f5f5',
                                                    color: isExpanded || comentarios[cl.clausula_codigo]
                                                        ? '#1e88e5' : '#bbb',
                                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    fontSize: 13,
                                                }}
                                            >
                                                <i className="fa fa-comment-alt" />
                                            </button>
                                        </div>

                                        {/* Expandable comment area */}
                                        {isExpanded && (
                                            <div style={{ padding: '0 14px 12px', borderTop: '1px solid #f0f0f0' }}>
                                                <textarea
                                                    autoFocus
                                                    placeholder="Observaciones, evidencias, notas del auditor..."
                                                    value={comentarios[cl.clausula_codigo] || ''}
                                                    onChange={e => handleComentarioChange(cl.clausula_codigo, e.target.value)}
                                                    style={{
                                                        width: '100%', minHeight: 70, padding: '8px 10px',
                                                        borderRadius: 8, border: '1.5px solid #e0e0e0',
                                                        fontSize: 12, resize: 'vertical', fontFamily: 'inherit',
                                                        outline: 'none', boxSizing: 'border-box', marginTop: 10,
                                                    }}
                                                    onFocus={e => e.target.style.borderColor = '#ff8a00'}
                                                    onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                                                />
                                            </div>
                                        )}

                                        {/* Saved comment preview (collapsed) */}
                                        {!isExpanded && comentarios[cl.clausula_codigo] && (
                                            <div style={{
                                                padding: '4px 14px 10px 74px',
                                                fontSize: 11, color: '#888', fontStyle: 'italic',
                                            }}>
                                                <i className="fa fa-comment-alt" style={{ marginRight: 4, color: '#1e88e5' }} />
                                                {comentarios[cl.clausula_codigo].slice(0, 80)}
                                                {comentarios[cl.clausula_codigo].length > 80 && '…'}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div style={{
                    padding: '12px 24px', borderTop: '1px solid #f0f0f0',
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    background: '#fafafa', flexShrink: 0,
                }}>
                    <span style={{ fontSize: 12, color: '#aaa' }}>
                        <i className="fa fa-save" style={{ marginRight: 4 }} />
                        Los cambios se guardan automáticamente
                    </span>
                    <div style={{ display: 'flex', gap: 10 }}>
                        {/* Botón Finalizar y Notificar */}
                        <button
                            disabled={finalizando}
                            onClick={async () => {
                                if (!auditoria?.id) return;
                                setFinalizando(true);
                                try {
                                    const res = await axios.post(`/api/auditorias/${auditoria.id}/finalizar`);
                                    if (res.data.ok) {
                                        const hayErrores = res.data.errores?.length > 0;
                                        Swal.fire({
                                            icon: hayErrores ? 'warning' : 'success',
                                            title: '¡Auditoría finalizada!',
                                            html: `<b>${res.data.pct_global}%</b> cumplimiento global<br>` +
                                                  `<small>${res.data.message}</small>` +
                                                  (hayErrores ? `<br><small style="color:#e53935">Errores: ${res.data.errores.join(', ')}</small>` : ''),
                                            timer: 5000,
                                            showConfirmButton: hayErrores,
                                        });
                                        onClose();
                                    }
                                } catch(e) {
                                    Swal.fire({ icon: 'error', title: 'Error', text: e.response?.data?.message || 'No se pudo finalizar' });
                                } finally {
                                    setFinalizando(false);
                                }
                            }}
                            style={{
                                padding: '8px 18px', borderRadius: 8, cursor: finalizando ? 'wait' : 'pointer',
                                border: '1.5px solid #5c6bc0', background: '#ede7f6',
                                color: '#5c6bc0', fontWeight: 700, fontSize: 13,
                                display: 'flex', alignItems: 'center', gap: 6,
                            }}
                        >
                            <i className={`fa ${finalizando ? 'fa-spinner fa-spin' : 'fa-paper-plane'}`} />
                            {finalizando ? 'Enviando…' : 'Finalizar y Notificar'}
                        </button>
                        <button className="btn-orange" onClick={onClose} style={{ padding: '8px 22px' }}>
                            <i className="fa fa-check" style={{ marginRight: 6 }} />
                            Listo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ══════════════════════════════════════════════════════════════════
 |  Componente Principal
 ══════════════════════════════════════════════════════════════════ */
export default function Auditoria() {
    const [sidebarOpen, setSidebarOpen] = useState(() => window.innerWidth > 700);
    const [activeTab, setActiveTab]     = useState('cumplimiento');
    const [loading, setLoading]         = useState(true);

    /* —— datos —— */
    const [normas,      setNormas]      = useState([]);
    const [documentos,  setDocumentos]  = useState([]);
    const [auditorias,  setAuditorias]  = useState([]);
    const [hallazgos,   setHallazgos]   = useState([]);
    const [compliance,  setCompliance]  = useState({ normas: [], global: { compliance: 0, total: 0, vigente: 0 } });

    /* —— modales —— */
    const [showNormaModal,     setShowNormaModal]     = useState(false);
    const [showAuditoriaModal, setShowAuditoriaModal] = useState(false);
    const [showHallazgoModal,  setShowHallazgoModal]  = useState(false);
    const [showAssignModal,    setShowAssignModal]    = useState(false);

    /* —— formularios —— */
    const [normaForm,     setNormaForm]     = useState({ codigo: '', descripcion: '' });
    const [auditoriaForm, setAuditoriaForm] = useState({ titulo: '', auditor: '', fecha: '', descripcion: '' });
    const [hallazgoForm,  setHallazgoForm]  = useState({ titulo: '', descripcion: '', prioridad: 'media' });
    const [editingHallazgo, setEditingHallazgo] = useState(null);

    /* —— edición de norma —— */
    const [editingNorma,      setEditingNorma]      = useState(null);
    const [showEditNormaModal, setShowEditNormaModal] = useState(false);
    const [normaEditForm,     setNormaEditForm]     = useState({ codigo: '', descripcion: '' });

    /* —— desvincular doc-norma —— */
    const [showDesvinModal, setShowDesvinModal] = useState(false);
    const [desvinDoc,       setDesvinDoc]       = useState('');
    const [desvinNorma,     setDesvinNorma]     = useState('');

    /* —— asignación —— */
    const [assignDoc,   setAssignDoc]   = useState('');
    const [assignNorma, setAssignNorma] = useState('');

    /* —— saving flags —— */
    const [saving, setSaving] = useState(false);

    /* —— evaluación de cláusulas —— */
    const [showEvalModal,  setShowEvalModal]  = useState(false);
    const [evalAuditoria,  setEvalAuditoria]  = useState(null);

    /* ── Carga inicial ──────────────────────────────────────────── */
    const fetchAll = useCallback(async (signal) => {
        setLoading(true);
        try {
            const [docsRes, normasRes, hallRes, audRes, compRes] = await Promise.all([
                axios.get('/api/documentos',          { signal }),
                axios.get('/api/normas',               { signal }),
                axios.get('/auditoria/hallazgos',      { signal }),
                axios.get('/api/auditorias',           { signal }),
                axios.get('/api/auditoria/compliance', { signal }),
            ]);
            setDocumentos(docsRes.data.data   || docsRes.data   || []);
            setNormas(normasRes.data.data      || normasRes.data || []);
            if (hallRes.data.ok)    setHallazgos(hallRes.data.data   || []);
            if (audRes.data.ok)     setAuditorias(audRes.data.data   || []);
            if (compRes.data.ok)    setCompliance(compRes.data);
        } catch (err) {
            if (axios.isCancel(err) || err.name === 'CanceledError') return; // navegación limpia
            console.error('fetchAll error:', err);
        }
        setLoading(false);
    }, []);

    useEffect(() => {
        const ctrl = new AbortController();
        fetchAll(ctrl.signal);
        return () => ctrl.abort(); // cancela requests pendientes al desmontar
    }, [fetchAll]);

    /* ── Refrescar solo compliance (tras asignar norma) ─────────── */
    const refreshCompliance = async () => {
        try {
            const res = await axios.get('/api/auditoria/compliance');
            if (res.data.ok) setCompliance(res.data);
        } catch (_) {}
    };

    /* ══════════════════════════════════════════════════════════════
     |  Normas handlers
     ══════════════════════════════════════════════════════════════ */
    const handleCreateNorma = async (e) => {
        e.preventDefault();
        if (!normaForm.codigo.trim() || !normaForm.descripcion.trim()) {
            return Alert.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Código y descripción son obligatorios.' });
        }
        setSaving(true);
        try {
            const res = await axios.post('/api/normas', normaForm);
            if (res.data.ok) {
                setNormas(prev => [...prev, res.data.norma]);
                setShowNormaModal(false);
                setNormaForm({ codigo: '', descripcion: '' });
                Alert.fire({ icon: 'success', title: 'Norma creada', timer: 1800, showConfirmButton: false });
            } else {
                Alert.fire({ icon: 'error', title: 'Error', text: res.data.message });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error del servidor', text: err.response?.data?.message || 'No se pudo crear la norma.' });
        }
        setSaving(false);
    };

    const handleEditNorma = (n) => {
        setEditingNorma(n);
        setNormaEditForm({ codigo: n.Codigo_norma, descripcion: n.Descripcion || '' });
        setShowEditNormaModal(true);
    };

    const handleUpdateNorma = async (e) => {
        e.preventDefault();
        if (!normaEditForm.codigo.trim()) return;
        setSaving(true);
        try {
            const res = await axios.put(`/api/normas/${editingNorma.idNorma}`, normaEditForm);
            if (res.data.ok) {
                setNormas(prev => prev.map(n =>
                    n.idNorma === editingNorma.idNorma
                        ? { ...n, Codigo_norma: normaEditForm.codigo, Descripcion: normaEditForm.descripcion }
                        : n
                ));
                await refreshCompliance();
                setShowEditNormaModal(false);
                setEditingNorma(null);
                Alert.fire({ icon: 'success', title: 'Norma actualizada', timer: 1800, showConfirmButton: false });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo actualizar.' });
        }
        setSaving(false);
    };

    const handleDeleteNorma = async (id, codigo) => {
        const result = await Alert.fire({
            icon: 'warning',
            title: '¿Eliminar norma?',
            html: `<p>Se eliminará <strong>"${codigo}"</strong> y se desvinculará de todos sus documentos y evaluaciones.</p>`,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e53935',
        });
        if (!result.isConfirmed) return;
        try {
            const res = await axios.delete(`/api/normas/${id}`);
            if (res.data.ok) {
                setNormas(prev => prev.filter(n => n.idNorma !== id));
                await refreshCompliance();
                Alert.fire({ icon: 'success', title: 'Norma eliminada', timer: 1500, showConfirmButton: false });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar la norma.' });
        }
    };

    const handleUnassignNorma = async (e) => {
        e.preventDefault();
        if (!desvinDoc || !desvinNorma) {
            return Alert.fire({ icon: 'warning', title: 'Selección incompleta', text: 'Selecciona un documento y una norma.' });
        }
        setSaving(true);
        try {
            const res = await axios.delete(`/api/documentos/${desvinDoc}/normas/${desvinNorma}`);
            if (res.data.ok) {
                setShowDesvinModal(false);
                setDesvinDoc(''); setDesvinNorma('');
                await refreshCompliance();
                Alert.fire({ icon: 'success', title: '¡Desvinculado!', text: 'Documento desvinculado de la norma.', timer: 2000, showConfirmButton: false });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo desvincular.' });
        }
        setSaving(false);
    };

    /* ══════════════════════════════════════════════════════════════
     |  Asignación Documento ↔ Norma
     ══════════════════════════════════════════════════════════════ */
    const handleAssignNorma = async (e) => {
        e.preventDefault();
        if (!assignDoc || !assignNorma) {
            return Alert.fire({ icon: 'warning', title: 'Selección incompleta', text: 'Selecciona un documento y una norma.' });
        }
        setSaving(true);
        try {
            const res = await axios.post(`/auditoria/documento/${assignDoc}/assign-norma`, { norma_id: assignNorma });
            if (res.data.ok) {
                setShowAssignModal(false);
                setAssignDoc(''); setAssignNorma('');
                await refreshCompliance();
                Alert.fire({ icon: 'success', title: '¡Vinculado!', text: 'Documento vinculado a la norma correctamente.', timer: 2000, showConfirmButton: false });
            } else {
                Alert.fire({ icon: 'warning', title: 'Advertencia', text: res.data.message });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo vincular.' });
        }
        setSaving(false);
    };

    /* ══════════════════════════════════════════════════════════════
     |  Auditorías handlers
     ══════════════════════════════════════════════════════════════ */
    const handleCreateAuditoria = async (e) => {
        e.preventDefault();
        if (!auditoriaForm.titulo.trim()) {
            return Alert.fire({ icon: 'warning', title: 'Campo requerido', text: 'El título de la auditoría es obligatorio.' });
        }
        setSaving(true);
        try {
            const res = await axios.post('/api/auditorias', auditoriaForm);
            if (res.data.ok) {
                setAuditorias(prev => [res.data.auditoria, ...prev]);
                setShowAuditoriaModal(false);
                setAuditoriaForm({ titulo: '', auditor: '', fecha: '', descripcion: '' });
                Alert.fire({ icon: 'success', title: 'Auditoría creada', timer: 1800, showConfirmButton: false });
            } else {
                Alert.fire({ icon: 'error', title: 'Error', text: res.data.message });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error del servidor', text: err.response?.data?.message || 'No se pudo crear la auditoría.' });
        }
        setSaving(false);
    };

    const handleDeleteAuditoria = async (id, titulo) => {
        const result = await Alert.fire({
            icon: 'warning',
            title: '¿Eliminar auditoría?',
            html: `<p>Se eliminará <strong>"${titulo}"</strong> y todos sus documentos asociados.</p>`,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e53935',
        });
        if (!result.isConfirmed) return;
        try {
            const res = await axios.delete(`/api/auditorias/${id}`);
            if (res.data.ok) {
                setAuditorias(prev => prev.filter(a => a.id !== id));
                Alert.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar la auditoría.' });
        }
    };

    /* ══════════════════════════════════════════════════════════════
     |  Hallazgos handlers
     ══════════════════════════════════════════════════════════════ */
    const openNewHallazgo = () => {
        setEditingHallazgo(null);
        setHallazgoForm({ titulo: '', descripcion: '', prioridad: 'media' });
        setShowHallazgoModal(true);
    };

    const openEditHallazgo = (h) => {
        setEditingHallazgo(h);
        setHallazgoForm({ titulo: h.titulo, descripcion: h.descripcion || '', prioridad: h.prioridad || 'media' });
        setShowHallazgoModal(true);
    };

    const handleSaveHallazgo = async (e) => {
        e.preventDefault();
        if (!hallazgoForm.titulo.trim()) {
            return Alert.fire({ icon: 'warning', title: 'Campo requerido', text: 'El título del hallazgo es obligatorio.' });
        }
        setSaving(true);
        try {
            let res;
            if (editingHallazgo) {
                res = await axios.put(`/auditoria/hallazgos/${editingHallazgo.id}`, hallazgoForm);
                if (res.data.ok) setHallazgos(prev => prev.map(h => h.id === res.data.hallazgo.id ? res.data.hallazgo : h));
            } else {
                res = await axios.post('/auditoria/hallazgos', hallazgoForm);
                if (res.data.ok) setHallazgos(prev => [res.data.hallazgo, ...prev]);
            }
            setShowHallazgoModal(false);
            Alert.fire({ icon: 'success', title: editingHallazgo ? 'Hallazgo actualizado' : 'Hallazgo registrado', timer: 1800, showConfirmButton: false });
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo guardar el hallazgo.' });
        }
        setSaving(false);
    };

    const handleDeleteHallazgo = async (id, titulo) => {
        const result = await Alert.fire({
            icon: 'warning',
            title: '¿Eliminar hallazgo?',
            text: `"${titulo}"`,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e53935',
        });
        if (!result.isConfirmed) return;
        try {
            await axios.delete(`/auditoria/hallazgos/${id}`);
            setHallazgos(prev => prev.filter(h => h.id !== id));
            Alert.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar el hallazgo.' });
        }
    };

    /* ══════════════════════════════════════════════════════════════
     |  Imprimir / Reporte
     ══════════════════════════════════════════════════════════════ */
    const handlePrintReport = () => {
        const printWin = window.open('', '_blank');
        const hallazgoRows = hallazgos.map(h => `
            <tr>
                <td>${h.titulo}</td>
                <td>${h.descripcion || '—'}</td>
                <td><span class="badge badge-${h.prioridad}">${h.prioridad}</span></td>
                <td>${new Date(h.created_at).toLocaleDateString('es-CO')}</td>
            </tr>`).join('');

        const normaRows = compliance.normas?.map(n => {
            const isEval = n.fuente === 'evaluacion';
            return `
            <tr>
                <td>${n.codigo}</td>
                <td>${isEval ? (n.fuente === 'evaluacion' ? '📋 Evaluación' : '📄 Documentos') : '📄 Documentos'}</td>
                <td style="color:#43a047">${isEval ? n.conformes : n.vigente}</td>
                <td style="color:#e53935">${isEval ? n.no_conformes : n.caducado}</td>
                <td style="color:#f57c00">${isEval ? n.observaciones : n.por_vencer}</td>
                <td><strong>${n.compliance}%</strong></td>
            </tr>`;
        }).join('') || '';

        printWin.document.write(`<!DOCTYPE html><html><head>
            <title>Reporte de Auditoría - DracoCert</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; padding: 30px; }
                h1 { color: #ff8a00; border-bottom: 2px solid #ff8a00; padding-bottom: 10px; }
                h2 { color: #555; font-size: 16px; margin-top: 30px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                th { background: #ff8a00; color: #fff; padding: 8px 12px; text-align: left; }
                td { padding: 8px 12px; border-bottom: 1px solid #eee; }
                tr:hover td { background: #f9f9f9; }
                .badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
                .badge-alta  { background: #fdecea; color: #d32f2f; }
                .badge-media { background: #fff8e1; color: #f57c00; }
                .badge-baja  { background: #e8f5e9; color: #388e3c; }
                .stat-box { display: inline-block; background: #fff3e0; border: 1px solid #ffd54f;
                    padding: 10px 20px; border-radius: 8px; margin: 5px; text-align: center; }
                .stat-num { font-size: 28px; font-weight: bold; color: #ff8a00; }
                @media print { body { padding: 15px; } }
            </style>
        </head><body>
            <h1><i>DracoCert</i> — Reporte de Auditoría ISO</h1>
            <p style="color:#888">Generado el ${new Date().toLocaleDateString('es-CO', { dateStyle: 'full' })}</p>

            <div>
                <div class="stat-box"><div class="stat-num">${compliance.global?.compliance ?? 0}%</div><div>Cumplimiento Global</div></div>
                <div class="stat-box"><div class="stat-num">${compliance.global?.total ?? 0}</div><div>Total Documentos</div></div>
                <div class="stat-box"><div class="stat-num">${compliance.global?.vigente ?? 0}</div><div>Documentos Vigentes</div></div>
                <div class="stat-box"><div class="stat-num">${hallazgos.length}</div><div>Hallazgos</div></div>
            </div>

            <h2>Cumplimiento por Norma ISO</h2>
            <table><thead><tr><th>Norma</th><th>Total Docs</th><th>Vigentes</th><th>Caducados</th><th>Cumplimiento</th></tr></thead>
            <tbody>${normaRows || '<tr><td colspan="5">Sin datos de cumplimiento</td></tr>'}</tbody></table>

            <h2>Hallazgos Registrados (${hallazgos.length})</h2>
            <table><thead><tr><th>Título</th><th>Descripción</th><th>Prioridad</th><th>Fecha</th></tr></thead>
            <tbody>${hallazgoRows || '<tr><td colspan="4">Sin hallazgos</td></tr>'}</tbody></table>

            <p style="margin-top:40px; color:#aaa; font-size:12px">DracoCert — Sistema de Gestión ISO para PYMEs · Universidad Antonio José Camacho</p>
        </body></html>`);
        printWin.document.close();
        setTimeout(() => { printWin.focus(); printWin.print(); }, 400);
    };

    /* ══════════════════════════════════════════════════════════════
     |  Tabs config
     ══════════════════════════════════════════════════════════════ */
    const tabs = [
        { key: 'cumplimiento', label: 'Cumplimiento ISO',  icon: 'fa-chart-pie' },
        { key: 'auditorias',   label: 'Auditorías',        icon: 'fa-clipboard-check' },
        { key: 'hallazgos',    label: 'Hallazgos',         icon: 'fa-exclamation-triangle' },
        { key: 'logs',         label: 'Historial',         icon: 'fa-history' },
    ];

    /* ══════════════════════════════════════════════════════════════
     |  RENDER
     ══════════════════════════════════════════════════════════════ */
    return (
        <div className="gestor-app">
            {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}
            <Sidebar open={sidebarOpen} onSelect={(key, url) => {
                if (key === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (url) window.location.href = url;
            }} />

            <div className="main-content">
                <Header
                    userName={typeof window !== 'undefined' ? (window.currentUser || 'Usuario') : 'Usuario'}
                    onToggleSidebar={() => setSidebarOpen(p => !p)}
                    onOpenAdd={activeTab === 'hallazgos' ? openNewHallazgo : undefined}
                />

                {/* ── Tab Bar ─────────────────────────────────────────── */}
                <div style={{
                    borderBottom: '2px solid #f0f0f0', background: '#fff',
                    padding: '0 24px', display: 'flex', gap: 0, overflowX: 'auto',
                }}>
                    {tabs.map(tab => (
                        <button key={tab.key} onClick={() => setActiveTab(tab.key)} style={{
                            border: 'none', background: 'none', cursor: 'pointer',
                            padding: '14px 18px', fontSize: 13, fontWeight: 700,
                            color: activeTab === tab.key ? '#ff8a00' : '#888',
                            borderBottom: activeTab === tab.key ? '3px solid #ff8a00' : '3px solid transparent',
                            marginBottom: -2, display: 'flex', alignItems: 'center', gap: 7,
                            whiteSpace: 'nowrap', transition: 'color 0.2s',
                        }}>
                            <i className={`fa ${tab.icon}`} />
                            {tab.label}
                            {tab.key === 'hallazgos' && hallazgos.length > 0 && (
                                <span style={{
                                    background: hallazgos.some(h => h.prioridad === 'alta') ? '#e53935' : '#ff8a00',
                                    color: '#fff', borderRadius: 20, padding: '1px 7px', fontSize: 10, fontWeight: 800,
                                }}>
                                    {hallazgos.length}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {/* ── Barra de exportación contextual ─────────────────── */}
                {(activeTab === 'cumplimiento' || activeTab === 'hallazgos' || activeTab === 'auditorias') && (
                    <div style={{
                        padding: '10px 24px', background: '#fafafa',
                        borderBottom: '1px solid #f0f0f0',
                        display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap',
                    }}>
                        <span style={{ fontSize: 12, color: '#aaa', fontWeight: 600, marginRight: 4 }}>
                            <i className="fa fa-download" style={{ marginRight: 4 }} />Exportar:
                        </span>
                        {(activeTab === 'cumplimiento' || activeTab === 'auditorias') && (
                            <>
                                <AuditExportBtn href="/reportes/pdf/cumplimiento"   icon="fa-file-pdf"   label="PDF Cumplimiento" color="#e53935" />
                                <AuditExportBtn href="/reportes/excel/cumplimiento" icon="fa-file-excel" label="Excel ISO"        color="#2e7d32" />
                            </>
                        )}
                        {activeTab === 'hallazgos' && (
                            <>
                                <AuditExportBtn href="/reportes/pdf/hallazgos"   icon="fa-file-pdf"   label="PDF Hallazgos"   color="#e53935" />
                                <AuditExportBtn href="/reportes/excel/hallazgos" icon="fa-file-excel" label="Excel Hallazgos" color="#2e7d32" />
                            </>
                        )}
                        <AuditExportBtn href="/reportes/excel/documentos" icon="fa-file-excel" label="Excel Documentos" color="#1565c0" />
                    </div>
                )}

                {/* ── Contenido ───────────────────────────────────────── */}
                <div className="content">
                    {loading && (
                        <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}>
                            <i className="fa fa-spinner fa-spin" style={{ fontSize: 32, color: '#ff8a00' }} />
                        </div>
                    )}

                    {/* ══ TAB: Cumplimiento ISO ══════════════════════════ */}
                    {!loading && activeTab === 'cumplimiento' && (
                        <div>
                            {/* KPI global */}
                            <div style={{
                                display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                                gap: 16, marginBottom: 24,
                            }}>
                                {[
                                    { label: 'Cumplimiento Global', val: `${compliance.global?.compliance ?? 0}%`, icon: 'fa-check-circle', color: '#ff8a00', bg: '#fff3e0' },
                                    { label: 'Total Documentos',    val: compliance.global?.total   ?? 0,    icon: 'fa-file-alt',     color: '#1e88e5', bg: '#e3f2fd' },
                                    { label: 'Vigentes',            val: compliance.global?.vigente ?? 0,    icon: 'fa-check',        color: '#43a047', bg: '#e8f5e9' },
                                    { label: 'Hallazgos',           val: hallazgos.length,                   icon: 'fa-exclamation-triangle', color: '#e53935', bg: '#fdecea' },
                                ].map(kpi => (
                                    <div key={kpi.label} className="table-card" style={{ padding: 20, display: 'flex', alignItems: 'center', gap: 14 }}>
                                        <div style={{
                                            width: 48, height: 48, borderRadius: 12,
                                            background: kpi.bg, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            fontSize: 20, color: kpi.color, flexShrink: 0,
                                        }}>
                                            <i className={`fa ${kpi.icon}`} />
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 22, fontWeight: 800, color: '#333' }}>{kpi.val}</div>
                                            <div style={{ fontSize: 12, color: '#888', fontWeight: 600 }}>{kpi.label}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Circular progress por norma */}
                            <div className="table-card" style={{ marginBottom: 24 }}>
                                <div style={{ padding: '16px 20px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <h3 style={{ margin: 0, fontSize: 15, fontWeight: 800, color: '#333' }}>
                                        <i className="fa fa-chart-pie" style={{ color: '#ff8a00', marginRight: 8 }} />
                                        Cumplimiento por Norma ISO
                                    </h3>
                                    <button className="btn-orange" style={{ fontSize: 13, padding: '7px 16px' }}
                                        onClick={handlePrintReport}>
                                        <i className="fa fa-print" style={{ marginRight: 6 }} />
                                        Generar Reporte
                                    </button>
                                </div>

                                {compliance.normas?.length === 0 ? (
                                    <div style={{ padding: 40, textAlign: 'center', color: '#aaa' }}>
                                        <i className="fa fa-info-circle" style={{ fontSize: 32, marginBottom: 12 }} />
                                        <p>No hay normas configuradas. Crea normas ISO y vincúlalas a documentos.</p>
                                        <button className="btn-orange" onClick={() => { setActiveTab('auditorias'); setShowNormaModal(true); }}>
                                            <i className="fa fa-plus" style={{ marginRight: 6 }} />
                                            Crear primera norma
                                        </button>
                                    </div>
                                ) : (
                                    <div style={{ padding: 24, display: 'flex', flexWrap: 'wrap', gap: 32, justifyContent: 'center' }}>
                                        {compliance.normas.map(n => {
                                            const cfg = getISOColor(n.codigo);
                                            return (
                                                <div key={n.id} style={{ textAlign: 'center' }}>
                                                    <CircularProgress value={n.compliance} label={n.codigo} color={cfg.accent} size={120} />
                                                    <div style={{ marginTop: 8, fontSize: 12, color: '#888' }}>
                                                        {n.fuente === 'evaluacion'
                                                            ? `${n.conformes ?? 0}/${n.total_cl ?? 0} cláusulas conformes`
                                                            : `${n.vigente ?? 0}/${n.total ?? 0} docs vigentes`
                                                        }
                                                    </div>
                                                    {n.fuente === 'evaluacion' && (
                                                        <div style={{ fontSize: 10, color: '#5c6bc0', marginTop: 2 }}>Evaluación de cláusulas</div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                        <div style={{ textAlign: 'center' }}>
                                            <CircularProgress value={compliance.global?.compliance ?? 0} label="Global" color="#ff8a00" size={120} />
                                            <div style={{ marginTop: 8, fontSize: 12, color: '#888' }}>
                                                {compliance.global?.vigente}/{compliance.global?.total} total vigentes
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Tabla detallada por norma */}
                            {compliance.normas?.length > 0 && (
                                <div className="table-card">
                                    <div style={{ padding: '16px 20px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <h3 style={{ margin: 0, fontSize: 15, fontWeight: 800, color: '#333' }}>
                                            <i className="fa fa-table" style={{ color: '#ff8a00', marginRight: 8 }} />
                                            Detalle por Norma
                                        </h3>
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            <button className="btn-outline" style={{ fontSize: 12 }} onClick={() => setShowAssignModal(true)}>
                                                <i className="fa fa-link" style={{ marginRight: 6 }} />
                                                Vincular Documento
                                            </button>
                                            <button className="btn-outline" style={{ fontSize: 12, borderColor: '#f57c00', color: '#f57c00' }} onClick={() => setShowDesvinModal(true)}>
                                                <i className="fa fa-unlink" style={{ marginRight: 6 }} />
                                                Desvincular
                                            </button>
                                        </div>
                                    </div>
                                    <div className="table-wrapper">
                                        <table className="user-table">
                                            <thead>
                                                <tr>
                                                    <th>Norma</th>
                                                    <th>Fuente</th>
                                                    <th>Conformes</th>
                                                    <th>No Conformes</th>
                                                    <th>Observaciones</th>
                                                    <th>Cumplimiento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {compliance.normas.map(n => {
                                                    const cfg = getISOColor(n.codigo);
                                                    const isEval = n.fuente === 'evaluacion';
                                                    return (
                                                        <tr key={n.id}>
                                                            <td>
                                                                <span style={{
                                                                    display: 'inline-flex', alignItems: 'center', gap: 8,
                                                                    background: cfg.bg, color: cfg.accent,
                                                                    padding: '4px 12px', borderRadius: 20,
                                                                    fontSize: 12, fontWeight: 700,
                                                                }}>
                                                                    <i className={`fa ${cfg.icon}`} />
                                                                    {n.codigo}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span style={{ fontSize: 11, color: isEval ? '#5c6bc0' : '#78909c', fontWeight: 700 }}>
                                                                    {isEval ? '📋 Evaluación' : '📄 Documentos'}
                                                                </span>
                                                            </td>
                                                            <td style={{ color: '#43a047', fontWeight: 700 }}>
                                                                {isEval ? n.conformes : n.vigente}
                                                                <span style={{ color: '#aaa', fontWeight: 400, fontSize: 11 }}>
                                                                    {isEval ? `/${n.total_cl}` : `/${n.total} docs`}
                                                                </span>
                                                            </td>
                                                            <td style={{ color: '#e53935', fontWeight: 700 }}>
                                                                {isEval ? n.no_conformes : n.caducado}
                                                            </td>
                                                            <td style={{ color: '#f57c00', fontWeight: 700 }}>
                                                                {isEval ? n.observaciones : n.por_vencer}
                                                            </td>
                                                            <td>
                                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                                    <div style={{ flex: 1, background: '#eee', borderRadius: 10, height: 8 }}>
                                                                        <div style={{
                                                                            width: `${n.compliance}%`, height: '100%',
                                                                            background: n.compliance >= 80 ? '#43a047' : n.compliance >= 50 ? '#f57c00' : '#e53935',
                                                                            borderRadius: 10, transition: 'width 0.8s ease',
                                                                        }} />
                                                                    </div>
                                                                    <span style={{ fontWeight: 800, minWidth: 36, color: cfg.accent }}>
                                                                        {n.compliance}%
                                                                    </span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ══ TAB: Auditorías ═══════════════════════════════ */}
                    {!loading && activeTab === 'auditorias' && (
                        <div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginBottom: 24 }}>
                                {/* Normas */}
                                <div className="table-card">
                                    <div style={{ padding: '14px 18px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <span style={{ fontWeight: 800, fontSize: 14, color: '#333' }}>
                                            <i className="fa fa-balance-scale" style={{ color: '#ff8a00', marginRight: 8 }} />
                                            Normas ISO Registradas
                                        </span>
                                        <button className="btn-orange" style={{ fontSize: 12, padding: '6px 12px' }} onClick={() => setShowNormaModal(true)}>
                                            <i className="fa fa-plus" style={{ marginRight: 5 }} /> Nueva Norma
                                        </button>
                                    </div>
                                    <div style={{ maxHeight: 220, overflowY: 'auto' }}>
                                        {normas.length === 0 ? (
                                            <div style={{ padding: 24, textAlign: 'center', color: '#bbb' }}>
                                                <i className="fa fa-inbox" style={{ fontSize: 28, marginBottom: 8 }} /><br />No hay normas
                                            </div>
                                        ) : (
                                            <table className="user-table">
                                                <thead>
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Descripción</th>
                                                        <th style={{ width: 80, textAlign: 'center' }}>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {normas.map(n => {
                                                        const cfg = getISOColor(n.Codigo_norma);
                                                        return (
                                                            <tr key={n.idNorma}>
                                                                <td>
                                                                    <span style={{ color: cfg.accent, fontWeight: 700 }}>
                                                                        <i className={`fa ${cfg.icon}`} style={{ marginRight: 6 }} />
                                                                        {n.Codigo_norma}
                                                                    </span>
                                                                </td>
                                                                <td style={{ color: '#666', fontSize: 12 }}>{n.Descripcion}</td>
                                                                <td>
                                                                    <div style={{ display: 'flex', gap: 4, justifyContent: 'center' }}>
                                                                        <button
                                                                            title="Editar norma"
                                                                            onClick={() => handleEditNorma(n)}
                                                                            style={{ background: '#e3f2fd', border: 'none', color: '#1e88e5', width: 28, height: 28, borderRadius: 6, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                                                        >
                                                                            <i className="fa fa-pencil-alt" style={{ fontSize: 11 }} />
                                                                        </button>
                                                                        <button
                                                                            title="Eliminar norma"
                                                                            onClick={() => handleDeleteNorma(n.idNorma, n.Codigo_norma)}
                                                                            style={{ background: '#fdecea', border: 'none', color: '#e53935', width: 28, height: 28, borderRadius: 6, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                                                        >
                                                                            <i className="fa fa-trash" style={{ fontSize: 11 }} />
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        )}
                                    </div>
                                </div>

                                {/* Vincular doc a norma */}
                                <div className="table-card">
                                    <div style={{ padding: '14px 18px', borderBottom: '1px solid #f0f0f0' }}>
                                        <span style={{ fontWeight: 800, fontSize: 14, color: '#333' }}>
                                            <i className="fa fa-link" style={{ color: '#ff8a00', marginRight: 8 }} />
                                            Vincular Documento ↔ Norma
                                        </span>
                                    </div>
                                    <div style={{ padding: 18 }}>
                                        <p style={{ fontSize: 12, color: '#888', marginBottom: 14 }}>
                                            Asigna documentos del gestor documental a una norma ISO para calcular el cumplimiento.
                                        </p>
                                        <button className="btn-orange" onClick={() => setShowAssignModal(true)} style={{ width: '100%' }}>
                                            <i className="fa fa-link" style={{ marginRight: 8 }} />
                                            Abrir panel de vinculación
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Lista auditorías */}
                            <div className="table-card">
                                <div style={{ padding: '14px 18px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontWeight: 800, fontSize: 14, color: '#333' }}>
                                        <i className="fa fa-clipboard-list" style={{ color: '#ff8a00', marginRight: 8 }} />
                                        Auditorías Registradas
                                        <span style={{ background: '#fff3e0', color: '#ff8a00', borderRadius: 20, padding: '2px 10px', fontSize: 12, fontWeight: 800, marginLeft: 10 }}>
                                            {auditorias.length}
                                        </span>
                                    </span>
                                    <button className="btn-orange" style={{ fontSize: 12, padding: '7px 14px' }} onClick={() => setShowAuditoriaModal(true)}>
                                        <i className="fa fa-plus" style={{ marginRight: 5 }} /> Nueva Auditoría
                                    </button>
                                </div>

                                {auditorias.length === 0 ? (
                                    <div style={{ padding: 40, textAlign: 'center', color: '#bbb' }}>
                                        <i className="fa fa-clipboard" style={{ fontSize: 40, marginBottom: 12 }} />
                                        <p>No hay auditorías creadas aún.</p>
                                        <button className="btn-orange" onClick={() => setShowAuditoriaModal(true)}>
                                            <i className="fa fa-plus" style={{ marginRight: 6 }} /> Crear primera auditoría
                                        </button>
                                    </div>
                                ) : (
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: 16, padding: 20 }}>
                                        {auditorias.map(a => (
                                            <div key={a.id} style={{
                                                border: '1.5px solid #f0f0f0', borderRadius: 12, padding: 18,
                                                background: '#fafafa', transition: 'box-shadow 0.2s',
                                            }}
                                                onMouseEnter={e => e.currentTarget.style.boxShadow = '0 4px 16px rgba(0,0,0,0.08)'}
                                                onMouseLeave={e => e.currentTarget.style.boxShadow = 'none'}
                                            >
                                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 }}>
                                                    <div>
                                                        <div style={{ fontWeight: 800, fontSize: 14, color: '#333', marginBottom: 4 }}>{a.titulo}</div>
                                                        {a.auditor && <div style={{ fontSize: 12, color: '#888' }}><i className="fa fa-user" style={{ marginRight: 4 }} />{a.auditor}</div>}
                                                    </div>
                                                    <button onClick={() => handleDeleteAuditoria(a.id, a.titulo)} style={{
                                                        background: '#fdecea', border: 'none', color: '#e53935',
                                                        width: 30, height: 30, borderRadius: '50%', cursor: 'pointer',
                                                        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                                                    }}>
                                                        <i className="fa fa-trash" style={{ fontSize: 12 }} />
                                                    </button>
                                                </div>
                                                {a.fecha && (
                                                    <div style={{ fontSize: 12, color: '#888', marginBottom: 6 }}>
                                                        <i className="fa fa-calendar" style={{ marginRight: 4 }} />
                                                        {new Date(a.fecha).toLocaleDateString('es-CO')}
                                                    </div>
                                                )}
                                                {a.descripcion && (
                                                    <div style={{ fontSize: 12, color: '#666', marginBottom: 8 }}>{a.descripcion}</div>
                                                )}
                                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 10 }}>
                                                    <div style={{ fontSize: 11, color: '#aaa' }}>
                                                        <i className="fa fa-file-alt" style={{ marginRight: 4 }} />
                                                        {a.documentos?.length ?? 0} documentos vinculados
                                                    </div>
                                                    <button
                                                        onClick={() => { setEvalAuditoria(a); setShowEvalModal(true); }}
                                                        style={{
                                                            background: '#fff3e0', border: '1.5px solid #ffcc80',
                                                            color: '#ff8a00', padding: '5px 12px', borderRadius: 8,
                                                            cursor: 'pointer', fontSize: 11, fontWeight: 800,
                                                            display: 'flex', alignItems: 'center', gap: 5,
                                                            transition: 'all 0.2s',
                                                        }}
                                                        onMouseEnter={e => { e.currentTarget.style.background = '#ff8a00'; e.currentTarget.style.color = '#fff'; }}
                                                        onMouseLeave={e => { e.currentTarget.style.background = '#fff3e0'; e.currentTarget.style.color = '#ff8a00'; }}
                                                    >
                                                        <i className="fa fa-clipboard-check" />
                                                        Evaluar Cláusulas
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* ══ TAB: Hallazgos ════════════════════════════════ */}
                    {!loading && activeTab === 'hallazgos' && (
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                                <div>
                                    <h2 style={{ margin: 0, fontSize: 18, fontWeight: 800, color: '#333' }}>
                                        <i className="fa fa-exclamation-triangle" style={{ color: '#e53935', marginRight: 8 }} />
                                        Gestión de Hallazgos
                                    </h2>
                                    <p style={{ margin: '4px 0 0', color: '#888', fontSize: 13 }}>
                                        Registra y gestiona las no conformidades y oportunidades de mejora
                                    </p>
                                </div>
                                <button className="btn-orange" onClick={openNewHallazgo}>
                                    <i className="fa fa-plus" style={{ marginRight: 8 }} /> Nuevo Hallazgo
                                </button>
                            </div>

                            {/* Resumen rápido */}
                            {hallazgos.length > 0 && (
                                <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
                                    {[
                                        { label: 'Alta',  count: hallazgos.filter(h => h.prioridad === 'alta').length,  color: '#e53935', bg: '#fdecea' },
                                        { label: 'Media', count: hallazgos.filter(h => h.prioridad === 'media').length, color: '#f57c00', bg: '#fff8e1' },
                                        { label: 'Baja',  count: hallazgos.filter(h => h.prioridad === 'baja').length,  color: '#388e3c', bg: '#e8f5e9' },
                                    ].map(s => (
                                        <div key={s.label} style={{
                                            background: s.bg, color: s.color, padding: '8px 20px', borderRadius: 30,
                                            fontWeight: 800, fontSize: 13, display: 'flex', alignItems: 'center', gap: 8,
                                        }}>
                                            <span style={{ fontSize: 20, fontWeight: 900 }}>{s.count}</span>
                                            Prioridad {s.label}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {hallazgos.length === 0 ? (
                                <div className="table-card" style={{ padding: 50, textAlign: 'center', color: '#bbb' }}>
                                    <i className="fa fa-check-circle" style={{ fontSize: 48, color: '#a5d6a7', marginBottom: 16 }} />
                                    <h3 style={{ color: '#aaa', fontWeight: 700 }}>¡Sin hallazgos pendientes!</h3>
                                    <p>Todos los procesos están bajo control.</p>
                                    <button className="btn-orange" onClick={openNewHallazgo}>
                                        <i className="fa fa-plus" style={{ marginRight: 6 }} /> Registrar hallazgo
                                    </button>
                                </div>
                            ) : (
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 16 }}>
                                    {hallazgos.map(h => (
                                        <div key={h.id} style={{
                                            background: '#fff', borderRadius: 12, padding: 20,
                                            border: `2px solid ${h.prioridad === 'alta' ? '#ffcdd2' : h.prioridad === 'media' ? '#ffe0b2' : '#c8e6c9'}`,
                                            boxShadow: '0 2px 8px rgba(0,0,0,0.05)',
                                            transition: 'transform 0.15s, box-shadow 0.15s',
                                        }}
                                            onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 6px 20px rgba(0,0,0,0.1)'; }}
                                            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.05)'; }}
                                        >
                                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 }}>
                                                <PriorityBadge prioridad={h.prioridad} />
                                                <div style={{ display: 'flex', gap: 6 }}>
                                                    <button onClick={() => openEditHallazgo(h)} style={{
                                                        background: '#e3f2fd', border: 'none', color: '#1e88e5',
                                                        width: 30, height: 30, borderRadius: '50%', cursor: 'pointer',
                                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    }}>
                                                        <i className="fa fa-edit" style={{ fontSize: 12 }} />
                                                    </button>
                                                    <button onClick={() => handleDeleteHallazgo(h.id, h.titulo)} style={{
                                                        background: '#fdecea', border: 'none', color: '#e53935',
                                                        width: 30, height: 30, borderRadius: '50%', cursor: 'pointer',
                                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    }}>
                                                        <i className="fa fa-trash" style={{ fontSize: 12 }} />
                                                    </button>
                                                </div>
                                            </div>
                                            <h4 style={{ margin: '0 0 8px', fontSize: 14, fontWeight: 800, color: '#333' }}>{h.titulo}</h4>
                                            {h.descripcion && (
                                                <p style={{ margin: '0 0 12px', fontSize: 13, color: '#666', lineHeight: 1.5 }}>{h.descripcion}</p>
                                            )}
                                            <div style={{ fontSize: 11, color: '#aaa' }}>
                                                <i className="fa fa-clock" style={{ marginRight: 4 }} />
                                                {new Date(h.created_at).toLocaleDateString('es-CO', { dateStyle: 'medium' })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ══ TAB: Historial ════════════════════════════════ */}
                    {!loading && activeTab === 'logs' && <ActivityLogs />}
                </div>
            </div>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Crear Norma
             ══════════════════════════════════════════════════════════ */}
            <Modal open={showNormaModal} onClose={() => setShowNormaModal(false)} title="Nueva Norma ISO">
                <form onSubmit={handleCreateNorma}>
                    <Field label="Código de Norma" required>
                        <input
                            style={inputStyle} required
                            placeholder="Ej: ISO 9001:2015, ISO 14001:2015, ISO 27001:2022"
                            value={normaForm.codigo}
                            onChange={e => setNormaForm(f => ({ ...f, codigo: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <Field label="Descripción" required>
                        <textarea
                            style={{ ...inputStyle, resize: 'vertical', minHeight: 80 }} required
                            placeholder="Descripción o alcance de la norma..."
                            value={normaForm.descripcion}
                            onChange={e => setNormaForm(f => ({ ...f, descripcion: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => setShowNormaModal(false)}>Cancelar</button>
                        <button type="submit" className="btn-orange" disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 6 }} />Guardando…</> : <><i className="fa fa-save" style={{ marginRight: 6 }} />Guardar Norma</>}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Editar Norma
             ══════════════════════════════════════════════════════════ */}
            <Modal open={showEditNormaModal} onClose={() => { setShowEditNormaModal(false); setEditingNorma(null); }} title="Editar Norma ISO">
                <form onSubmit={handleUpdateNorma}>
                    <Field label="Código de Norma" required>
                        <input
                            style={inputStyle} required
                            placeholder="Ej: ISO 9001:2015"
                            value={normaEditForm.codigo}
                            onChange={e => setNormaEditForm(f => ({ ...f, codigo: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <Field label="Descripción">
                        <textarea
                            style={{ ...inputStyle, resize: 'vertical', minHeight: 70 }}
                            placeholder="Descripción o alcance de la norma..."
                            value={normaEditForm.descripcion}
                            onChange={e => setNormaEditForm(f => ({ ...f, descripcion: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => { setShowEditNormaModal(false); setEditingNorma(null); }}>Cancelar</button>
                        <button type="submit" className="btn-orange" disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 6 }} />Guardando…</> : <><i className="fa fa-save" style={{ marginRight: 6 }} />Guardar Cambios</>}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Desvincular Documento de Norma
             ══════════════════════════════════════════════════════════ */}
            <Modal open={showDesvinModal} onClose={() => setShowDesvinModal(false)} title="Desvincular Documento de Norma ISO">
                <form onSubmit={handleUnassignNorma}>
                    <div style={{ background: '#fff8e1', border: '1px solid #ffe082', borderRadius: 8, padding: '10px 14px', marginBottom: 18, fontSize: 13, color: '#6d4c00' }}>
                        <i className="fa fa-info-circle" style={{ marginRight: 6 }} />
                        Selecciona el documento y la norma que deseas desvincular. Esta acción se puede revertir vinculando nuevamente.
                    </div>
                    <Field label="Documento" required>
                        <select
                            style={{ ...inputStyle, cursor: 'pointer' }} required
                            value={desvinDoc}
                            onChange={e => setDesvinDoc(e.target.value)}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        >
                            <option value="">— Seleccione un documento —</option>
                            {documentos.map(d => (
                                <option key={d.idDocumento || d.id} value={d.idDocumento || d.id}>
                                    {d.Nombre_Doc}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Norma ISO a desvincular" required>
                        <select
                            style={{ ...inputStyle, cursor: 'pointer' }} required
                            value={desvinNorma}
                            onChange={e => setDesvinNorma(e.target.value)}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        >
                            <option value="">— Seleccione una norma —</option>
                            {normas.map(n => (
                                <option key={n.idNorma} value={n.idNorma}>{n.Codigo_norma}</option>
                            ))}
                        </select>
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => setShowDesvinModal(false)}>Cancelar</button>
                        <button type="submit" style={{ background: '#f57c00', color: '#fff', border: 'none', padding: '9px 20px', borderRadius: 8, cursor: 'pointer', fontWeight: 700, fontSize: 13, display: 'flex', alignItems: 'center', gap: 6 }} disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" />Procesando…</> : <><i className="fa fa-unlink" />Desvincular</>}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Vincular Documento a Norma
             ══════════════════════════════════════════════════════════ */}
            <Modal open={showAssignModal} onClose={() => setShowAssignModal(false)} title="Vincular Documento a Norma ISO">
                <form onSubmit={handleAssignNorma}>
                    <div style={{ background: '#fff8e1', border: '1px solid #ffe082', borderRadius: 8, padding: '10px 14px', marginBottom: 18, fontSize: 13, color: '#6d4c00' }}>
                        <i className="fa fa-info-circle" style={{ marginRight: 6 }} />
                        Vincular un documento a una norma permite calcular el porcentaje de cumplimiento ISO.
                    </div>
                    <Field label="Documento" required>
                        <select
                            style={{ ...inputStyle, cursor: 'pointer' }} required
                            value={assignDoc}
                            onChange={e => setAssignDoc(e.target.value)}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        >
                            <option value="">— Seleccione un documento —</option>
                            {documentos.map(d => (
                                <option key={d.idDocumento || d.id} value={d.idDocumento || d.id}>
                                    {d.Nombre_Doc}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Norma ISO" required>
                        <select
                            style={{ ...inputStyle, cursor: 'pointer' }} required
                            value={assignNorma}
                            onChange={e => setAssignNorma(e.target.value)}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        >
                            <option value="">— Seleccione una norma —</option>
                            {normas.map(n => (
                                <option key={n.idNorma} value={n.idNorma}>{n.Codigo_norma}</option>
                            ))}
                        </select>
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => setShowAssignModal(false)}>Cancelar</button>
                        <button type="submit" className="btn-orange" disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 6 }} />Vinculando…</> : <><i className="fa fa-link" style={{ marginRight: 6 }} />Vincular</>}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Nueva / Editar Auditoría
             ══════════════════════════════════════════════════════════ */}
            <Modal open={showAuditoriaModal} onClose={() => setShowAuditoriaModal(false)} title="Nueva Auditoría">
                <form onSubmit={handleCreateAuditoria}>
                    <Field label="Título de la Auditoría" required>
                        <input
                            style={inputStyle} required
                            placeholder="Ej: Auditoría Q1 2026 — ISO 9001"
                            value={auditoriaForm.titulo}
                            onChange={e => setAuditoriaForm(f => ({ ...f, titulo: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <Field label="Auditor Responsable">
                            <input
                                style={inputStyle}
                                placeholder="Nombre del auditor"
                                value={auditoriaForm.auditor}
                                onChange={e => setAuditoriaForm(f => ({ ...f, auditor: e.target.value }))}
                                onFocus={e => e.target.style.borderColor = '#ff8a00'}
                                onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                            />
                        </Field>
                        <Field label="Fecha de Auditoría">
                            <input
                                type="date" style={inputStyle}
                                value={auditoriaForm.fecha}
                                onChange={e => setAuditoriaForm(f => ({ ...f, fecha: e.target.value }))}
                                onFocus={e => e.target.style.borderColor = '#ff8a00'}
                                onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                            />
                        </Field>
                    </div>
                    <Field label="Descripción / Alcance">
                        <textarea
                            style={{ ...inputStyle, resize: 'vertical', minHeight: 80 }}
                            placeholder="Alcance, objetivos y notas de la auditoría..."
                            value={auditoriaForm.descripcion}
                            onChange={e => setAuditoriaForm(f => ({ ...f, descripcion: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => setShowAuditoriaModal(false)}>Cancelar</button>
                        <button type="submit" className="btn-orange" disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 6 }} />Guardando…</> : <><i className="fa fa-save" style={{ marginRight: 6 }} />Crear Auditoría</>}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Evaluación de Cláusulas ISO
             ══════════════════════════════════════════════════════════ */}
            <EvaluacionModal
                open={showEvalModal}
                onClose={() => { setShowEvalModal(false); setEvalAuditoria(null); }}
                auditoria={evalAuditoria}
                normas={normas}
            />

            {/* ══════════════════════════════════════════════════════════
             |  MODAL: Nuevo / Editar Hallazgo
             ══════════════════════════════════════════════════════════ */}
            <Modal
                open={showHallazgoModal}
                onClose={() => setShowHallazgoModal(false)}
                title={editingHallazgo ? 'Editar Hallazgo' : 'Registrar Nuevo Hallazgo'}
            >
                <form onSubmit={handleSaveHallazgo}>
                    <Field label="Título del Hallazgo" required>
                        <input
                            style={inputStyle} required
                            placeholder="Describe brevemente el hallazgo encontrado"
                            value={hallazgoForm.titulo}
                            onChange={e => setHallazgoForm(f => ({ ...f, titulo: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <Field label="Descripción Detallada">
                        <textarea
                            style={{ ...inputStyle, resize: 'vertical', minHeight: 90 }}
                            placeholder="Detalles del hallazgo, evidencias, impacto observado..."
                            value={hallazgoForm.descripcion}
                            onChange={e => setHallazgoForm(f => ({ ...f, descripcion: e.target.value }))}
                            onFocus={e => e.target.style.borderColor = '#ff8a00'}
                            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                        />
                    </Field>
                    <Field label="Nivel de Prioridad">
                        <div style={{ display: 'flex', gap: 10 }}>
                            {[
                                { val: 'alta',  label: 'Alta',  color: '#e53935', bg: '#fdecea' },
                                { val: 'media', label: 'Media', color: '#f57c00', bg: '#fff8e1' },
                                { val: 'baja',  label: 'Baja',  color: '#388e3c', bg: '#e8f5e9' },
                            ].map(p => (
                                <button
                                    key={p.val}
                                    type="button"
                                    onClick={() => setHallazgoForm(f => ({ ...f, prioridad: p.val }))}
                                    style={{
                                        flex: 1, padding: '10px 0', border: '2px solid',
                                        borderColor: hallazgoForm.prioridad === p.val ? p.color : '#e0e0e0',
                                        background: hallazgoForm.prioridad === p.val ? p.bg : '#fff',
                                        color: hallazgoForm.prioridad === p.val ? p.color : '#999',
                                        borderRadius: 8, cursor: 'pointer', fontWeight: 700, fontSize: 13,
                                        transition: 'all 0.2s',
                                    }}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
                        <button type="button" className="btn-outline" onClick={() => setShowHallazgoModal(false)}>Cancelar</button>
                        <button type="submit" className="btn-orange" disabled={saving}>
                            {saving ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 6 }} />Guardando…</> : <><i className="fa fa-save" style={{ marginRight: 6 }} />{editingHallazgo ? 'Actualizar' : 'Registrar'}</>}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
