import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';

/**
 * Wrapper de SweetAlert2 que garantiza que las alertas siempre
 * se muestren POR ENCIMA del modal (z-index: 9999 → usamos 100000).
 */
const Alert = Swal.mixin({
    didOpen: () => {
        const container = document.querySelector('.swal2-container');
        if (container) container.style.zIndex = '100000';
    },
});

const isExpired = dateStr => dateStr && new Date(dateStr) < new Date();

// ─── Icono según extensión del archivo ───────────────────────────────────────

function fileIcon(filename) {
    const ext = (filename || '').split('.').pop().toLowerCase();
    const map = {
        pdf:  { icon: 'fa-file-pdf',       color: '#e53935' },
        doc:  { icon: 'fa-file-word',       color: '#1565c0' },
        docx: { icon: 'fa-file-word',       color: '#1565c0' },
        xls:  { icon: 'fa-file-excel',      color: '#2e7d32' },
        xlsx: { icon: 'fa-file-excel',      color: '#2e7d32' },
        ppt:  { icon: 'fa-file-powerpoint', color: '#bf360c' },
        pptx: { icon: 'fa-file-powerpoint', color: '#bf360c' },
        txt:  { icon: 'fa-file-alt',        color: '#546e7a' },
        zip:  { icon: 'fa-file-archive',    color: '#6d4c41' },
        jpg:  { icon: 'fa-file-image',      color: '#00838f' },
        jpeg: { icon: 'fa-file-image',      color: '#00838f' },
        png:  { icon: 'fa-file-image',      color: '#00838f' },
    };
    return map[ext] ?? { icon: 'fa-file', color: '#78909c' };
}

// ─── Componente principal ─────────────────────────────────────────────────────

export default function DocumentDetailsModal({ doc, onClose }) {
    const [loading,    setLoading]    = useState(true);
    const [versiones,  setVersiones]  = useState([]);
    const [file,       setFile]       = useState(null);
    const [descripcion,setDescripcion]= useState('');
    const [uploading,  setUploading]  = useState(false);
    const [progress,   setProgress]   = useState(0);
    const fileRef = useRef(null);

    // Cargar versiones al abrir
    useEffect(() => {
        if (! doc?.idDocumento) return;
        loadVersiones();
    }, [doc]);

    const loadVersiones = () => {
        setLoading(true);
        axios.get(`/api/documentos/${doc.idDocumento}`)
            .then(r => setVersiones(r.data?.versiones ?? []))
            .catch(err => console.error('Error cargando versiones:', err))
            .finally(() => setLoading(false));
    };

    // ─── Subir nueva versión ──────────────────────────────────────────────────

    const handleUpload = async e => {
        e.preventDefault();
        if (! file)              return Alert.fire('Aviso', 'Selecciona un archivo.', 'warning');
        if (! descripcion.trim())return Alert.fire('Aviso', 'Escribe una descripción del cambio.', 'warning');

        const fd = new FormData();
        fd.append('archivo',     file);
        fd.append('descripcion', descripcion);

        setUploading(true);
        setProgress(0);

        try {
            await axios.post(`/api/documentos/${doc.idDocumento}/versiones`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: ev =>
                    setProgress(Math.round((ev.loaded * 100) / ev.total)),
            });

            Alert.fire({
                icon: 'success', title: '¡Versión subida!',
                timer: 1800, showConfirmButton: false,
            });

            setDescripcion('');
            setFile(null);
            if (fileRef.current) fileRef.current.value = '';
            loadVersiones();

        } catch (err) {
            const msg = err.response?.data?.error || 'Error al subir la versión.';
            Alert.fire('Error', msg, 'error');
        } finally {
            setUploading(false);
            setProgress(0);
        }
    };

    // ─── Descarga de versión ──────────────────────────────────────────────────

    const handleDownloadVersion = (versionId, filename) => {
        const a = document.createElement('a');
        a.href     = `/api/documentos/${doc.idDocumento}/versiones/${versionId}/descargar`;
        a.download = filename || 'documento';
        a.target   = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // ─── Render ───────────────────────────────────────────────────────────────

    // Versiones en orden descendente (más nueva primero)
    const versionesDesc = [...versiones].reverse();

    return (
        <div style={styles.overlay}>
            <div style={styles.modal}>

                {/* ── Header ── */}
                <div style={styles.header}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <i className="fa fa-file-alt" style={{ color: '#fff', fontSize: 18 }}></i>
                        <div>
                            <div style={{ fontWeight: 800, color: '#fff', fontSize: 15 }}>
                                {doc?.Nombre_Doc}
                            </div>
                            <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12 }}>
                                Historial de versiones
                            </div>
                        </div>
                    </div>
                    <button onClick={onClose} style={styles.closeBtn}>
                        <i className="fa fa-times"></i>
                    </button>
                </div>

                {/* ── Cuerpo ── */}
                <div style={styles.body}>

                    {/* ── Columna izquierda: timeline de versiones ── */}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <h4 style={styles.sectionTitle}>
                            <i className="fa fa-code-branch" style={{ color: '#ff8a00' }}></i>
                            Historial de versiones
                        </h4>

                        {loading ? (
                            <div style={styles.centeredMsg}>
                                <i className="fa fa-spinner fa-spin" style={{ fontSize: 24 }}></i>
                                <p style={{ marginTop: 8, fontSize: 13 }}>Cargando versiones...</p>
                            </div>
                        ) : versiones.length === 0 ? (
                            <div style={styles.emptyTimeline}>
                                <i className="fa fa-history" style={{ fontSize: 28, display: 'block', marginBottom: 8 }}></i>
                                <span style={{ fontSize: 13 }}>No hay versiones registradas aún</span>
                            </div>
                        ) : (
                            <div style={{ position: 'relative' }}>
                                {/* Línea vertical */}
                                <div style={styles.timelineLine}></div>

                                <div style={{ display: 'flex', flexDirection: 'column', gap: 0 }}>
                                    {versionesDesc.map((v, i) => {
                                        const isLatest = i === 0;
                                        const fi = fileIcon(v.nombre_archivo);
                                        return (
                                            <div key={v.idVersion || i} style={{ display: 'flex', gap: 12, paddingBottom: 16, position: 'relative' }}>

                                                {/* Punto en la línea */}
                                                <div style={{
                                                    width: 30, height: 30, borderRadius: '50%',
                                                    background:  isLatest ? '#ff8a00' : '#f0f0f0',
                                                    border:      `2px solid ${isLatest ? '#ff8a00' : '#e0e0e0'}`,
                                                    display:     'flex', alignItems: 'center', justifyContent: 'center',
                                                    flexShrink:  0, zIndex: 1,
                                                }}>
                                                    <span style={{ fontSize: 10, fontWeight: 800, color: isLatest ? '#fff' : '#aaa' }}>
                                                        v{v.numero_Version}
                                                    </span>
                                                </div>

                                                {/* Tarjeta de versión */}
                                                <div style={{
                                                    flex: 1,
                                                    background: isLatest ? '#fff8f0' : '#fafafa',
                                                    borderRadius: 10, padding: '10px 14px',
                                                    border: `1px solid ${isLatest ? '#ffd18a' : '#f0f0f0'}`,
                                                }}>
                                                    {/* Fila superior: versión + badge + botón descargar */}
                                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8, flexWrap: 'wrap' }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                            <span style={{ fontWeight: 700, color: '#333', fontSize: 13 }}>
                                                                Versión {v.numero_Version}
                                                            </span>
                                                            {isLatest && (
                                                                <span style={styles.latestBadge}>Actual</span>
                                                            )}
                                                        </div>

                                                        {v.url && (
                                                            <button
                                                                onClick={() => handleDownloadVersion(v.idVersion, v.nombre_archivo)}
                                                                style={styles.downloadBtn}
                                                                title="Descargar esta versión"
                                                            >
                                                                <i className="fa fa-download" style={{ marginRight: 4 }}></i>
                                                                Descargar
                                                            </button>
                                                        )}
                                                    </div>

                                                    {/* Archivo adjunto */}
                                                    {v.nombre_archivo && (
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 6 }}>
                                                            <i className={`fa ${fi.icon}`} style={{ color: fi.color, fontSize: 14 }}></i>
                                                            <span style={{ fontSize: 12, color: '#555', fontWeight: 500 }}>
                                                                {v.nombre_archivo}
                                                            </span>
                                                        </div>
                                                    )}

                                                    {/* Meta: usuario + fecha */}
                                                    <div style={{ fontSize: 11, color: '#aaa', marginTop: 6 }}>
                                                        <i className="fa fa-user" style={{ marginRight: 4 }}></i>
                                                        {v.Nombre_Usuario || 'Sistema'}
                                                        <span style={{ margin: '0 6px' }}>·</span>
                                                        <i className="fa fa-calendar" style={{ marginRight: 4 }}></i>
                                                        {v.Fecha_cambio || (v.created_at
                                                            ? new Date(v.created_at).toLocaleDateString('es-CO')
                                                            : '—'
                                                        )}
                                                    </div>

                                                    {/* Descripción del cambio */}
                                                    {v.Descripcion_Cambio && (
                                                        <div style={{ fontSize: 12, color: '#666', marginTop: 6, fontStyle: 'italic' }}>
                                                            "{v.Descripcion_Cambio}"
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ── Columna derecha: subir versión + info del documento ── */}
                    <div style={{ width: window.innerWidth <= 700 ? '100%' : 300, flexShrink: 0 }}>

                        {/* Formulario nueva versión */}
                        <h4 style={styles.sectionTitle}>
                            <i className="fa fa-upload" style={{ color: '#ff8a00' }}></i>
                            Nueva versión
                        </h4>

                        <form onSubmit={handleUpload} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>

                            <div>
                                <label style={styles.label}>Archivo</label>
                                <input
                                    type="file"
                                    ref={fileRef}
                                    onChange={e => setFile(e.target.files?.[0] ?? null)}
                                    style={styles.fileInput}
                                />
                                {file && (
                                    <div style={{ fontSize: 11, color: '#888', marginTop: 4 }}>
                                        <i className="fa fa-check-circle" style={{ color: '#26a69a', marginRight: 4 }}></i>
                                        {file.name}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label style={styles.label}>Descripción del cambio</label>
                                <textarea
                                    value={descripcion}
                                    onChange={e => setDescripcion(e.target.value)}
                                    placeholder="¿Qué cambió en esta versión?"
                                    style={styles.textarea}
                                />
                            </div>

                            {uploading && (
                                <div>
                                    <div style={styles.progressTrack}>
                                        <div style={{ ...styles.progressFill, width: `${progress}%` }} />
                                    </div>
                                    <div style={{ textAlign: 'center', fontSize: 11, color: '#888', marginTop: 3 }}>
                                        {progress}%
                                    </div>
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={uploading}
                                style={{
                                    ...styles.submitBtn,
                                    background: uploading ? '#f0f0f0' : 'linear-gradient(135deg,#ff8a00,#e67300)',
                                    color:      uploading ? '#aaa' : '#fff',
                                    cursor:     uploading ? 'not-allowed' : 'pointer',
                                }}
                            >
                                {uploading
                                    ? <><i className="fa fa-spinner fa-spin"></i> Subiendo...</>
                                    : <><i className="fa fa-cloud-upload-alt" style={{ marginRight: 6 }}></i>Subir versión</>
                                }
                            </button>
                        </form>

                        {/* Info del documento */}
                        <div style={styles.infoCard}>
                            <div style={{ fontWeight: 700, color: '#555', marginBottom: 8, fontSize: 13 }}>
                                <i className="fa fa-info-circle" style={{ color: '#ff8a00', marginRight: 6 }}></i>
                                Información
                            </div>
                            <InfoRow label="Creado"   value={doc?.Fecha_creacion || '—'} />
                            <InfoRow label="Revisión" value={doc?.Fecha_Revision || '—'} />
                            <InfoRow
                                label="Caducidad"
                                value={doc?.Fecha_Caducidad || '—'}
                                valueStyle={{ color: isExpired(doc?.Fecha_Caducidad) ? '#e53935' : '#777', fontWeight: isExpired(doc?.Fecha_Caducidad) ? 700 : 400 }}
                            />
                            <InfoRow label="Versiones" value={versiones.length} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Sub-componentes ──────────────────────────────────────────────────────────

const InfoRow = ({ label, value, valueStyle = {} }) => (
    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, padding: '4px 0', borderBottom: '1px solid #f5f5f5' }}>
        <span style={{ color: '#999' }}>{label}</span>
        <span style={{ color: '#777', fontWeight: 500, ...valueStyle }}>{value}</span>
    </div>
);

// ─── Estilos ──────────────────────────────────────────────────────────────────

const styles = {
    overlay: {
        position: 'fixed', inset: 0,
        background: 'rgba(0,0,0,0.45)',
        display: 'flex',
        alignItems: window.innerWidth <= 700 ? 'flex-end' : 'center',
        justifyContent: 'center',
        zIndex: 9999,
        padding: window.innerWidth <= 700 ? 0 : 16,
    },
    modal: {
        width: window.innerWidth <= 700 ? '100%' : 'min(900px, 96%)',
        maxHeight: window.innerWidth <= 700 ? '95vh' : '90vh',
        overflowY: 'auto', background: '#fff',
        borderRadius: window.innerWidth <= 700 ? '20px 20px 0 0' : 14,
        boxShadow: '0 8px 40px rgba(0,0,0,0.22)',
    },
    header: {
        padding: '16px 20px',
        borderBottom: '1px solid #f0f0f0',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        background: 'linear-gradient(135deg,#ff8a00,#e67300)',
        borderRadius: '14px 14px 0 0',
    },
    closeBtn: {
        background: 'rgba(255,255,255,0.2)', border: 'none', color: '#fff',
        width: 32, height: 32, borderRadius: 8, cursor: 'pointer',
        fontSize: 16, display: 'flex', alignItems: 'center', justifyContent: 'center',
    },
    body: {
        padding: 20, display: 'flex', gap: 20, flexWrap: 'wrap',
        // stack en mobile
        flexDirection: window.innerWidth <= 700 ? 'column' : 'row',
    },
    sectionTitle: {
        margin: '0 0 14px', fontSize: 14, fontWeight: 700, color: '#333',
        display: 'flex', alignItems: 'center', gap: 8,
    },
    timelineLine: {
        position: 'absolute', left: 15, top: 8, bottom: 8,
        width: 2, background: '#f0f0f0',
    },
    centeredMsg: {
        textAlign: 'center', padding: 30, color: '#aaa',
    },
    emptyTimeline: {
        textAlign: 'center', padding: 30, color: '#ccc',
        border: '1.5px dashed #eee', borderRadius: 10,
    },
    latestBadge: {
        background: '#ff8a00', color: '#fff',
        fontSize: 10, padding: '2px 8px',
        borderRadius: 99, fontWeight: 700,
    },
    downloadBtn: {
        background: '#5c6bc014', border: 'none', borderRadius: 6,
        color: '#5c6bc0', fontSize: 12, fontWeight: 600,
        cursor: 'pointer', padding: '4px 10px',
        display: 'flex', alignItems: 'center',
        transition: 'background 0.2s',
    },
    label: {
        display: 'block', fontSize: 12, fontWeight: 600,
        color: '#666', marginBottom: 4,
    },
    fileInput: {
        width: '100%', border: '1px solid #e0e0e0',
        borderRadius: 8, padding: '7px 10px',
        fontSize: 12, boxSizing: 'border-box',
    },
    textarea: {
        width: '100%', minHeight: 80,
        border: '1px solid #e0e0e0', borderRadius: 8,
        padding: '8px 10px', fontSize: 12, resize: 'vertical',
        boxSizing: 'border-box', outline: 'none', fontFamily: 'inherit',
    },
    progressTrack: {
        height: 6, background: '#f0f0f0', borderRadius: 99, overflow: 'hidden',
    },
    progressFill: {
        height: '100%', background: 'linear-gradient(90deg,#ff8a00,#e67300)',
        transition: 'width 0.3s',
    },
    submitBtn: {
        padding: '10px', border: 'none', borderRadius: 8,
        fontWeight: 700, fontSize: 13,
    },
    infoCard: {
        marginTop: 16, padding: 14,
        background: '#f9f9f9', borderRadius: 10,
    },
};
