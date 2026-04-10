import React, { useEffect, useState, useCallback } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import Sidebar from './Sidebar';
import Header from './Header';

// ── Etiquetas legibles por grupo ─────────────────────────────────────────────
const GRUPO_LABELS = {
    documentos:     { label: 'Documentos ISO',  icon: 'fa-file-alt',       color: '#ff8a00' },
    capacitaciones: { label: 'Capacitaciones',  icon: 'fa-graduation-cap', color: '#26a69a' },
    sistema:        { label: 'Sistema',          icon: 'fa-cog',            color: '#5c6bc0' },
    reportes:       { label: 'Reportes',         icon: 'fa-chart-bar',      color: '#78909c' },
};

// ── Subcomponente para campos de tipo lista (tags) ────────────────────────────
// Separado en su propio componente para poder usar useState correctamente.
function TagInput({ valor, onChange }) {
    const [newTag, setNewTag] = useState('');
    const tags = valor.split(',').filter(Boolean);

    function addTag() {
        const t = newTag.trim().toLowerCase().replace(/^\./, '');
        if (!t || tags.includes(t)) { setNewTag(''); return; }
        onChange([...tags, t].join(','));
        setNewTag('');
    }

    function removeTag(tag) {
        onChange(tags.filter(t => t !== tag).join(','));
    }

    return (
        <div>
            <div style={styles.tagContainer}>
                {tags.map(tag => (
                    <span key={tag} style={styles.tag}>
                        .{tag}
                        <button onClick={() => removeTag(tag)} style={styles.tagRemove} title="Eliminar">
                            ×
                        </button>
                    </span>
                ))}
                {tags.length === 0 && (
                    <span style={{ color: '#f87171', fontSize: 13 }}>
                        ⚠ Sin tipos permitidos — todos los archivos serán rechazados.
                    </span>
                )}
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                <input
                    type="text"
                    value={newTag}
                    onChange={e => setNewTag(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addTag())}
                    placeholder="ej: pdf  (Enter para agregar)"
                    style={{ ...styles.input, flex: 1 }}
                />
                <button onClick={addTag} style={styles.btnSecondary}>
                    <i className="fa fa-plus" /> Agregar
                </button>
            </div>
        </div>
    );
}

// ── Campo individual ──────────────────────────────────────────────────────────
function ConfigField({ item, onSave, onReset }) {
    // Todos los useState al nivel del componente — sin condicionales
    const [valor, setValor]   = useState(item.valor);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty]   = useState(false);

    useEffect(() => {
        setValor(item.valor);
        setDirty(false);
    }, [item.valor]);

    function handleChange(v) {
        setValor(v);
        setDirty(v !== item.valor);
    }

    async function handleSave() {
        setSaving(true);
        try {
            await onSave(item.clave, valor);
            setDirty(false);
        } finally {
            setSaving(false);
        }
    }

    async function handleReset() {
        const result = await Swal.fire({
            title: '¿Restaurar valor por defecto?',
            text: `Se restaurará "${item.etiqueta}" a su valor original.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, restaurar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#5c6bc0',
        });
        if (!result.isConfirmed) return;
        setSaving(true);
        try {
            await onReset(item.clave);
        } finally {
            setSaving(false);
        }
    }

    // ── Render del input según tipo (sin useState aquí) ───────────────────────
    function renderInput() {
        if (item.tipo === 'bool') {
            return (
                <label style={styles.toggle}>
                    <input
                        type="checkbox"
                        checked={valor === '1'}
                        onChange={e => handleChange(e.target.checked ? '1' : '0')}
                        style={{ display: 'none' }}
                    />
                    <span style={{ ...styles.toggleTrack, background: valor === '1' ? '#26a69a' : '#ccc' }}>
                        <span style={{ ...styles.toggleThumb, left: valor === '1' ? 22 : 2 }} />
                    </span>
                    <span style={{ marginLeft: 10, fontWeight: 600, color: valor === '1' ? '#26a69a' : '#999' }}>
                        {valor === '1' ? 'Activo' : 'Inactivo'}
                    </span>
                </label>
            );
        }

        if (item.tipo === 'list') {
            // TagInput es un componente real → puede usar sus propios hooks
            return <TagInput valor={valor} onChange={handleChange} />;
        }

        // int o string
        return (
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <input
                    type={item.tipo === 'int' ? 'number' : 'text'}
                    value={valor}
                    min={item.tipo === 'int' ? 0 : undefined}
                    onChange={e => handleChange(e.target.value)}
                    style={styles.input}
                />
                {item.unidad && <span style={styles.unit}>{item.unidad}</span>}
            </div>
        );
    }

    return (
        <div style={{ ...styles.fieldCard, borderLeft: dirty ? '3px solid #ff8a00' : '3px solid transparent' }}>
            <div style={styles.fieldHeader}>
                <div style={{ flex: 1 }}>
                    <span style={styles.fieldLabel}>{item.etiqueta}</span>
                    {item.descripcion && <p style={styles.fieldDesc}>{item.descripcion}</p>}
                </div>
                <div style={styles.fieldActions}>
                    {dirty && (
                        <button onClick={handleSave} disabled={saving} style={styles.btnSave}>
                            {saving
                                ? <i className="fa fa-spinner fa-spin" />
                                : <i className="fa fa-check" />}
                            {' Guardar'}
                        </button>
                    )}
                    <button onClick={handleReset} disabled={saving} style={styles.btnReset} title="Restaurar valor por defecto">
                        <i className="fa fa-undo" />
                    </button>
                </div>
            </div>
            <div style={styles.fieldInput}>
                {renderInput()}
            </div>
        </div>
    );
}

// ── Componente principal ──────────────────────────────────────────────────────
export default function Configuracion() {
    const [grupos, setGrupos]           = useState({});
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [grupoActivo, setGrupoActivo] = useState('documentos');

    const fetchConfig = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.get('/api/configuracion');
            setGrupos(data);
            const keys = Object.keys(data);
            if (keys.length && !data[grupoActivo]) setGrupoActivo(keys[0]);
        } catch {
            setError('No se pudo cargar la configuración.');
        } finally {
            setLoading(false);
        }
    }, []); // eslint-disable-line

    useEffect(() => { fetchConfig(); }, [fetchConfig]);

    async function handleSave(clave, valor) {
        try {
            await axios.put(`/api/configuracion/${clave}`, { valor });
            setGrupos(prev => {
                const next = { ...prev };
                for (const g of Object.keys(next)) {
                    next[g] = next[g].map(item =>
                        item.clave === clave ? { ...item, valor } : item
                    );
                }
                return next;
            });
            Swal.fire({
                icon: 'success', title: 'Guardado',
                text: 'Parámetro actualizado. Los cambios aplican en la próxima solicitud.',
                timer: 2200, showConfirmButton: false,
            });
        } catch (err) {
            // Laravel devuelve errors.campo[0] en 422, no errors.error
            const msg = err.response?.data?.error
                ?? err.response?.data?.message
                ?? 'Error al guardar el parámetro.';
            Swal.fire({ icon: 'error', title: 'Error de validación', text: msg });
        }
    }

    async function handleReset(clave) {
        try {
            const { data } = await axios.post(`/api/configuracion/reset/${clave}`);
            setGrupos(prev => {
                const next = { ...prev };
                for (const g of Object.keys(next)) {
                    next[g] = next[g].map(item =>
                        item.clave === clave ? { ...item, valor: data.valor } : item
                    );
                }
                return next;
            });
            Swal.fire({ icon: 'success', title: 'Restaurado', text: 'Valor restaurado al predeterminado.', timer: 1800, showConfirmButton: false });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo restaurar el parámetro.' });
        }
    }

    function handleSidebarSelect(key, url) {
        if (key === '__toggle_sidebar__') { setSidebarOpen(o => !o); return; }
        if (url) window.location.href = url;
    }

    const gruposDisponibles = Object.keys(grupos);
    const itemsActivos      = grupos[grupoActivo] ?? [];

    return (
        <div style={styles.layout}>
            <Sidebar open={sidebarOpen} onSelect={handleSidebarSelect} />
            <div style={styles.main}>
                <Header title="Parametrización del Sistema" onToggleSidebar={() => setSidebarOpen(o => !o)} />

                <div style={styles.content}>
                    <div style={styles.pageHeader}>
                        <div>
                            <h2 style={styles.pageTitle}>
                                <i className="fa fa-sliders-h" style={{ marginRight: 10, color: '#5c6bc0' }} />
                                Parametrización del Sistema
                            </h2>
                            <p style={styles.pageSubtitle}>
                                Configura los parámetros operativos sin modificar el código. Los cambios aplican de inmediato en el servidor.
                            </p>
                        </div>
                    </div>

                    {loading && (
                        <div style={styles.centered}>
                            <i className="fa fa-spinner fa-spin" style={{ fontSize: 32, color: '#5c6bc0' }} />
                            <p style={{ marginTop: 12, color: '#666' }}>Cargando configuración…</p>
                        </div>
                    )}

                    {error && !loading && (
                        <div style={styles.errorBox}>
                            <i className="fa fa-exclamation-triangle" style={{ marginRight: 8 }} />
                            {error}
                            <button onClick={fetchConfig} style={{ ...styles.btnSecondary, marginLeft: 12 }}>
                                <i className="fa fa-redo" /> Reintentar
                            </button>
                        </div>
                    )}

                    {!loading && !error && (
                        <div style={styles.body}>
                            {/* ── Tabs ── */}
                            <div style={styles.tabs}>
                                {gruposDisponibles.map(g => {
                                    const meta   = GRUPO_LABELS[g] ?? { label: g, icon: 'fa-cog', color: '#888' };
                                    const active = g === grupoActivo;
                                    return (
                                        <button key={g} onClick={() => setGrupoActivo(g)} style={{
                                            ...styles.tab,
                                            borderBottom: active ? `3px solid ${meta.color}` : '3px solid transparent',
                                            color:        active ? meta.color : '#666',
                                            fontWeight:   active ? 700 : 400,
                                        }}>
                                            <i className={`fa ${meta.icon}`} style={{ marginRight: 6 }} />
                                            {meta.label}
                                            <span style={{
                                                ...styles.badge,
                                                background: active ? meta.color : '#eee',
                                                color:      active ? '#fff' : '#666',
                                            }}>
                                                {grupos[g]?.length ?? 0}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {/* ── Campos ── */}
                            <div style={styles.fields}>
                                {itemsActivos.length === 0 && (
                                    <p style={{ color: '#999', textAlign: 'center', padding: 40 }}>
                                        No hay parámetros en este grupo.
                                    </p>
                                )}
                                {itemsActivos.map(item => (
                                    <ConfigField
                                        key={item.clave}
                                        item={item}
                                        onSave={handleSave}
                                        onReset={handleReset}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// ── Estilos ───────────────────────────────────────────────────────────────────
const styles = {
    layout:       { display: 'flex', minHeight: '100vh', background: '#f4f6fb', fontFamily: 'Segoe UI, sans-serif' },
    main:         { flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' },
    content:      { flex: 1, padding: '24px', overflowY: 'auto' },
    pageHeader:   { marginBottom: 24 },
    pageTitle:    { fontSize: 22, fontWeight: 700, margin: 0, color: '#1a1a2e' },
    pageSubtitle: { fontSize: 14, color: '#666', marginTop: 4 },
    centered:     { display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '60px 0', color: '#555' },
    errorBox:     { background: '#fff3f3', border: '1px solid #f5c6cb', borderRadius: 8, padding: '16px 20px', color: '#721c24', display: 'flex', alignItems: 'center' },
    body:         { background: '#fff', borderRadius: 12, boxShadow: '0 2px 12px rgba(0,0,0,0.07)' },
    tabs:         { display: 'flex', borderBottom: '1px solid #e8ecf0', padding: '0 16px', gap: 4, flexWrap: 'wrap' },
    tab:          { background: 'none', border: 'none', padding: '14px 18px', cursor: 'pointer', fontSize: 14, display: 'flex', alignItems: 'center', gap: 4, transition: 'color 0.2s' },
    badge:        { fontSize: 11, padding: '2px 7px', borderRadius: 12, marginLeft: 4, fontWeight: 700 },
    fields:       { padding: '8px 0 16px' },
    fieldCard:    { padding: '18px 24px', borderBottom: '1px solid #f0f2f5', transition: 'border-left 0.2s' },
    fieldHeader:  { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16 },
    fieldLabel:   { fontWeight: 600, fontSize: 15, color: '#1a1a2e' },
    fieldDesc:    { fontSize: 13, color: '#6b7280', margin: '4px 0 0', lineHeight: 1.5 },
    fieldInput:   { marginTop: 12 },
    fieldActions: { display: 'flex', gap: 8, alignItems: 'center', flexShrink: 0 },
    input:        { border: '1.5px solid #d1d5db', borderRadius: 7, padding: '8px 12px', fontSize: 14, outline: 'none', minWidth: 180, color: '#1a1a2e' },
    unit:         { fontSize: 13, color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' },
    btnSave:      { background: '#26a69a', color: '#fff', border: 'none', borderRadius: 7, padding: '8px 16px', cursor: 'pointer', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4 },
    btnReset:     { background: '#f3f4f6', color: '#6b7280', border: '1px solid #d1d5db', borderRadius: 7, padding: '8px 10px', cursor: 'pointer', fontSize: 13 },
    btnSecondary: { background: '#f3f4f6', color: '#374151', border: '1px solid #d1d5db', borderRadius: 7, padding: '7px 14px', cursor: 'pointer', fontSize: 13, display: 'flex', alignItems: 'center', gap: 4 },
    toggle:       { display: 'flex', alignItems: 'center', cursor: 'pointer', userSelect: 'none' },
    toggleTrack:  { width: 44, height: 24, borderRadius: 12, position: 'relative', transition: 'background 0.3s', display: 'inline-block' },
    toggleThumb:  { width: 20, height: 20, background: '#fff', borderRadius: '50%', position: 'absolute', top: 2, transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' },
    tagContainer: { display: 'flex', flexWrap: 'wrap', gap: 6 },
    tag:          { background: '#ede9fe', color: '#5c3d9e', borderRadius: 6, padding: '4px 10px', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4 },
    tagRemove:    { background: 'none', border: 'none', cursor: 'pointer', color: '#7c3aed', fontSize: 16, lineHeight: 1, padding: '0 2px', fontWeight: 700 },
};
