import React, { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import Sidebar from './Sidebar.jsx';
import DocumentDetailsModal from './DocumentDetailsModal.jsx';
import TableSkeleton from './TableSkeleton.jsx';
import '../axiosSetup.js';

// ─── Metadatos de carpetas ISO ────────────────────────────────────────────────

const FOLDER_META = {
    iso9001:  { icon: 'fa-award',      color: '#ff8a00' },
    iso14001: { icon: 'fa-leaf',       color: '#26a69a' },
    iso27001: { icon: 'fa-shield-alt', color: '#5c6bc0' },
    general:  { icon: 'fa-folder',     color: '#78909c' },
};

// ─── Icono por extensión ──────────────────────────────────────────────────────

function fileIcon(filename) {
    const ext = (filename || '').split('.').pop().toLowerCase();
    const map = {
        pdf:  { icon: 'fa-file-pdf',        color: '#e53935' },
        doc:  { icon: 'fa-file-word',        color: '#1565c0' },
        docx: { icon: 'fa-file-word',        color: '#1565c0' },
        xls:  { icon: 'fa-file-excel',       color: '#2e7d32' },
        xlsx: { icon: 'fa-file-excel',       color: '#2e7d32' },
        ppt:  { icon: 'fa-file-powerpoint',  color: '#bf360c' },
        pptx: { icon: 'fa-file-powerpoint',  color: '#bf360c' },
        txt:  { icon: 'fa-file-alt',         color: '#546e7a' },
        zip:  { icon: 'fa-file-archive',     color: '#6d4c41' },
        jpg:  { icon: 'fa-file-image',       color: '#00838f' },
        jpeg: { icon: 'fa-file-image',       color: '#00838f' },
        png:  { icon: 'fa-file-image',       color: '#00838f' },
    };
    return map[ext] ?? { icon: 'fa-file', color: '#78909c' };
}

const isExpired   = d => d && new Date(d) < new Date();
const isExpiring  = d => {
    if (!d) return false;
    const diff = new Date(d) - new Date();
    return diff > 0 && diff < 30 * 24 * 60 * 60 * 1000;
};

// ─── Filtros vacíos por defecto ───────────────────────────────────────────────

const EMPTY_FILTERS = { fechaDesde: '', fechaHasta: '', estado: '', ordenar: 'reciente' };

// ─── Componente principal ─────────────────────────────────────────────────────

export default function Documentacion() {
    const [sidebarOpen,    setSidebarOpen]    = useState(() => window.innerWidth > 700);
    const [folders,        setFolders]        = useState([]);
    const [selectedFolder, setSelectedFolder] = useState('general');
    const [documents,      setDocuments]      = useState([]);
    const [query,          setQuery]          = useState('');
    const [filters,        setFilters]        = useState(EMPTY_FILTERS);
    const [showFilters,    setShowFilters]    = useState(false);
    const [uploading,      setUploading]      = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [selectedDoc,    setSelectedDoc]    = useState(null);
    const [showModal,      setShowModal]      = useState(false);
    const [loadingDocs,    setLoadingDocs]    = useState(false);

    const fileInputRef = useRef(null);
    const [fileName,     setFileName]     = useState('');
    const [selectedFile, setSelectedFile] = useState(null);
    const debounceRef = useRef(null);

    useEffect(() => { fetchFolders(); }, []);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchDocuments(), 300);
        return () => clearTimeout(debounceRef.current);
    }, [query, selectedFolder, filters]);

    // ─── API calls ────────────────────────────────────────────────────────────

    const fetchFolders = () =>
        axios.get('/api/documentos/folders')
            .then(r => setFolders(r.data))
            .catch(e => {
                console.error(e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar las carpetas ISO. Recarga la página.', timer: 4000, showConfirmButton: false });
            });

    const fetchDocuments = useCallback(() => {
        const params = {
            folder:      selectedFolder,
            ...(query               && { q:           query }),
            ...(filters.fechaDesde  && { fecha_desde: filters.fechaDesde }),
            ...(filters.fechaHasta  && { fecha_hasta: filters.fechaHasta }),
            ...(filters.estado      && { estado:      filters.estado }),
            ...(filters.ordenar     && { ordenar:     filters.ordenar }),
        };
        setLoadingDocs(true);
        axios.get('/api/documentos', { params })
            .then(r => setDocuments(r.data))
            .catch(e => {
                console.error(e);
                Swal.fire({ icon: 'error', title: 'Error al cargar', text: 'No se pudieron cargar los documentos. Intenta de nuevo.', timer: 3500, showConfirmButton: false });
                setDocuments([]);
            })
            .finally(() => setLoadingDocs(false));
    }, [query, selectedFolder, filters]);

    // ─── Handlers ────────────────────────────────────────────────────────────

    const handleFileChange = e => {
        const f = e.target.files[0];
        if (f) { setSelectedFile(f); setFileName(f.name.replace(/\.[^.]+$/, '')); }
    };

    const handleUpload = async () => {
        if (!selectedFile)
            return Swal.fire({ icon: 'warning', title: 'Aviso', text: 'Selecciona un archivo primero.' });

        const docName = fileName.trim() || selectedFile.name;
        const fd = new FormData();
        fd.append('archivo',    selectedFile);
        fd.append('Nombre_Doc', docName);
        fd.append('folder',     selectedFolder);

        setUploading(true); setUploadProgress(0);
        try {
            await axios.post('/api/documentos/subir', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: e => setUploadProgress(Math.round((e.loaded * 100) / e.total)),
            });
            Swal.fire({ icon: 'success', title: '¡Documento subido!', text: `"${docName}" guardado correctamente.`, timer: 2000, showConfirmButton: false });
            setSelectedFile(null); setFileName('');
            if (fileInputRef.current) fileInputRef.current.value = '';
            fetchFolders(); fetchDocuments();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al subir', text: e.response?.data?.error || 'No se pudo subir el archivo.' });
        } finally { setUploading(false); setUploadProgress(0); }
    };

    const handleDelete = id => {
        Swal.fire({
            title: '¿Eliminar documento?',
            text: 'Se eliminará el archivo y todas sus versiones. Esta acción no se puede deshacer.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#e53935', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        }).then(r => {
            if (r.isConfirmed)
                axios.delete(`/api/documentos/${id}`)
                    .then(() => {
                        Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
                        fetchFolders(); fetchDocuments();
                    })
                    .catch(() => Swal.fire('Error', 'No se pudo eliminar el documento.', 'error'));
        });
    };

    const handleDownload = (docId, filename) => {
        const a = document.createElement('a');
        a.href = `/api/documentos/${docId}/descargar`;
        a.download = filename || 'documento';
        a.target = '_blank';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    };

    const handleFilterChange = (key, val) =>
        setFilters(prev => ({ ...prev, [key]: val }));

    const clearFilters = () => { setFilters(EMPTY_FILTERS); setQuery(''); };

    const activeFilterCount = [
        query,
        filters.fechaDesde,
        filters.fechaHasta,
        filters.estado,
        filters.ordenar !== 'reciente' ? filters.ordenar : '',
    ].filter(Boolean).length;

    const currentFolder = folders.find(f => f.id === selectedFolder);

    // ─── Render ───────────────────────────────────────────────────────────────

    return (
        <div style={S.root}>
            {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}
            <Sidebar open={sidebarOpen} onSelect={(key, url) => {
                if (key === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (url) window.location.href = url;
            }} />
            <div style={S.mainWrapper}>

                {/* ── Panel izquierdo ── */}
                <div style={S.leftPanel}>
                    <div style={S.explorerHeader}>
                        <i className="fa fa-folder-open" style={{ color: '#ff8a00', fontSize: 15 }}></i>
                        <span style={{ fontWeight: 800, fontSize: 13, color: '#333' }}>Repositorio ISO</span>
                    </div>

                    <div style={{ flex: 1, padding: '8px 0', overflowY: 'auto' }}>
                        {folders.map(f => {
                            const fi = FOLDER_META[f.id] || FOLDER_META.general;
                            const active = selectedFolder === f.id;
                            return (
                                <button key={f.id} onClick={() => setSelectedFolder(f.id)}
                                    style={{ ...S.folderBtn, background: active ? fi.color + '12' : 'transparent', borderLeft: active ? `3px solid ${fi.color}` : '3px solid transparent' }}>
                                    <div style={{ ...S.folderIconWrap, background: fi.color + '18' }}>
                                        <i className={`fa ${fi.icon}`} style={{ color: fi.color, fontSize: 14 }}></i>
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: active ? 700 : 600, fontSize: 13, color: active ? fi.color : '#444', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {f.Nombre}
                                        </div>
                                        <div style={{ fontSize: 11, color: '#aaa', marginTop: 1 }}>
                                            {f.count ?? 0} doc{f.count !== 1 ? 's' : ''}
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    {/* Upload */}
                    <div style={S.uploadBox}>
                        <div style={S.uploadTitle}>
                            <i className="fa fa-upload" style={{ color: '#ff8a00' }}></i> Subir documento
                        </div>
                        <input type="file" ref={fileInputRef} onChange={handleFileChange} style={{ display: 'none' }} />
                        <button onClick={() => fileInputRef.current?.click()} style={S.filePickerBtn}>
                            {selectedFile
                                ? <span style={{ color: '#333', fontWeight: 600 }}><i className="fa fa-file" style={{ color: '#ff8a00', marginRight: 4 }}></i>{selectedFile.name.length > 22 ? selectedFile.name.slice(0, 22) + '…' : selectedFile.name}</span>
                                : <><i className="fa fa-plus-circle" style={{ marginRight: 4 }}></i>Seleccionar archivo</>}
                        </button>
                        {selectedFile && (
                            <input type="text" value={fileName} onChange={e => setFileName(e.target.value)}
                                placeholder="Nombre del documento" style={S.nameInput} />
                        )}
                        {uploading && (
                            <div style={{ marginBottom: 6 }}>
                                <div style={S.progressTrack}><div style={{ ...S.progressFill, width: `${uploadProgress}%` }} /></div>
                                <div style={{ textAlign: 'center', fontSize: 11, color: '#888', marginTop: 2 }}>{uploadProgress}%</div>
                            </div>
                        )}
                        <button onClick={handleUpload} disabled={uploading || !selectedFile}
                            style={{ ...S.uploadBtn, background: uploading || !selectedFile ? '#f0f0f0' : 'linear-gradient(135deg,#ff8a00,#e67300)', color: uploading || !selectedFile ? '#aaa' : '#fff', cursor: uploading || !selectedFile ? 'not-allowed' : 'pointer' }}>
                            {uploading ? <><i className="fa fa-spinner fa-spin"></i> Subiendo...</> : <><i className="fa fa-cloud-upload-alt" style={{ marginRight: 4 }}></i>Subir</>}
                        </button>
                    </div>
                </div>

                {/* ── Panel derecho ── */}
                <div style={S.rightPanel}>

                    {/* Cabecera carpeta */}
                    <div style={S.tableHeader}>
                        {/* Botón hamburguesa para toggle del sidebar */}
                        <button
                            onClick={() => setSidebarOpen(p => !p)}
                            style={{ background: 'none', border: '1.5px solid #e8eaed', borderRadius: 8, padding: '7px 10px', cursor: 'pointer', color: '#555', fontSize: 15, lineHeight: 1, flexShrink: 0, transition: 'background .15s' }}
                            title={sidebarOpen ? 'Colapsar navegación' : 'Abrir navegación'}
                            onMouseEnter={e => e.currentTarget.style.background = '#f5f5f5'}
                            onMouseLeave={e => e.currentTarget.style.background = 'none'}
                        >
                            <i className="fa fa-bars" />
                        </button>

                        {currentFolder && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <div style={{ width: 40, height: 40, borderRadius: 10, background: (FOLDER_META[currentFolder.id]?.color ?? '#aaa') + '18', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <i className={`fa ${FOLDER_META[currentFolder.id]?.icon ?? 'fa-folder'}`} style={{ color: FOLDER_META[currentFolder.id]?.color ?? '#aaa', fontSize: 18 }}></i>
                                </div>
                                <div>
                                    <h2 style={{ margin: 0, fontSize: 18, fontWeight: 800, color: '#222' }}>{currentFolder.Nombre}</h2>
                                    <p style={{ margin: 0, fontSize: 12, color: '#888' }}>{currentFolder.descripcion}</p>
                                </div>
                            </div>
                        )}

                        {/* Conteo de resultados */}
                        <div style={{ fontSize: 12, color: '#999', marginLeft: 'auto', marginRight: 12, whiteSpace: 'nowrap' }}>
                            {loadingDocs ? '' : `${documents.length} resultado${documents.length !== 1 ? 's' : ''}`}
                        </div>
                    </div>

                    {/* ── Barra de búsqueda avanzada ── */}
                    <div style={S.searchCard}>

                        {/* Fila principal: input + botones */}
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                            <div style={{ position: 'relative', flex: 1, minWidth: 200 }}>
                                <i className="fa fa-search" style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', color: '#bbb', fontSize: 13, pointerEvents: 'none' }}></i>
                                <input
                                    type="text" value={query}
                                    onChange={e => setQuery(e.target.value)}
                                    placeholder="Buscar por nombre, código o palabra clave..."
                                    style={S.searchInput}
                                />
                                {query && (
                                    <button onClick={() => setQuery('')} style={S.clearInputBtn} title="Limpiar búsqueda">
                                        <i className="fa fa-times"></i>
                                    </button>
                                )}
                            </div>

                            {/* Toggle filtros avanzados */}
                            <button onClick={() => setShowFilters(v => !v)}
                                style={{ ...S.filterToggleBtn, background: showFilters ? '#ff8a0018' : '#f5f5f5', color: showFilters ? '#ff8a00' : '#666', borderColor: showFilters ? '#ff8a0044' : '#e0e0e0' }}>
                                <i className="fa fa-sliders-h" style={{ marginRight: 5 }}></i>
                                Filtros
                                {activeFilterCount > 0 && (
                                    <span style={S.filterBadge}>{activeFilterCount}</span>
                                )}
                            </button>

                            {/* Ordenar */}
                            <select value={filters.ordenar} onChange={e => handleFilterChange('ordenar', e.target.value)} style={S.selectSmall}>
                                <option value="reciente">↓ Más reciente</option>
                                <option value="antiguo">↑ Más antiguo</option>
                                <option value="nombre_az">A → Z</option>
                                <option value="nombre_za">Z → A</option>
                            </select>
                        </div>

                        {/* Panel de filtros avanzados (expandible) */}
                        {showFilters && (
                            <div style={S.advancedPanel}>
                                <div style={S.filtersGrid}>

                                    {/* Fecha creación desde */}
                                    <div style={S.filterGroup}>
                                        <label style={S.filterLabel}>
                                            <i className="fa fa-calendar-alt" style={{ marginRight: 5, color: '#ff8a00' }}></i>
                                            Creación desde
                                        </label>
                                        <input type="date" value={filters.fechaDesde}
                                            onChange={e => handleFilterChange('fechaDesde', e.target.value)}
                                            style={S.dateInput} />
                                    </div>

                                    {/* Fecha creación hasta */}
                                    <div style={S.filterGroup}>
                                        <label style={S.filterLabel}>
                                            <i className="fa fa-calendar-alt" style={{ marginRight: 5, color: '#ff8a00' }}></i>
                                            Creación hasta
                                        </label>
                                        <input type="date" value={filters.fechaHasta}
                                            onChange={e => handleFilterChange('fechaHasta', e.target.value)}
                                            style={S.dateInput} />
                                    </div>

                                    {/* Estado de caducidad */}
                                    <div style={S.filterGroup}>
                                        <label style={S.filterLabel}>
                                            <i className="fa fa-clock" style={{ marginRight: 5, color: '#ff8a00' }}></i>
                                            Estado
                                        </label>
                                        <select value={filters.estado}
                                            onChange={e => handleFilterChange('estado', e.target.value)}
                                            style={S.dateInput}>
                                            <option value="">Todos</option>
                                            <option value="vigente">✅ Vigentes</option>
                                            <option value="por_vencer">⚠️ Por vencer (30 días)</option>
                                            <option value="caducado">🔴 Caducados</option>
                                        </select>
                                    </div>

                                    {/* Botón limpiar */}
                                    <div style={{ ...S.filterGroup, justifyContent: 'flex-end' }}>
                                        <label style={{ ...S.filterLabel, visibility: 'hidden' }}>-</label>
                                        <button onClick={clearFilters} style={S.clearFiltersBtn}>
                                            <i className="fa fa-times-circle" style={{ marginRight: 5 }}></i>
                                            Limpiar filtros
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Chips de filtros activos */}
                        {activeFilterCount > 0 && (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 10 }}>
                                {query && <FilterChip label={`Texto: "${query}"`} onRemove={() => setQuery('')} />}
                                {filters.fechaDesde && <FilterChip label={`Desde: ${filters.fechaDesde}`} onRemove={() => handleFilterChange('fechaDesde', '')} />}
                                {filters.fechaHasta && <FilterChip label={`Hasta: ${filters.fechaHasta}`} onRemove={() => handleFilterChange('fechaHasta', '')} />}
                                {filters.estado && <FilterChip label={{ vigente: '✅ Vigentes', por_vencer: '⚠️ Por vencer', caducado: '🔴 Caducados' }[filters.estado]} onRemove={() => handleFilterChange('estado', '')} />}
                                {filters.ordenar !== 'reciente' && <FilterChip label={`Orden: ${{ antiguo: 'Más antiguo', nombre_az: 'A→Z', nombre_za: 'Z→A' }[filters.ordenar]}`} onRemove={() => handleFilterChange('ordenar', 'reciente')} />}
                            </div>
                        )}
                    </div>

                    {/* ── Tabla de documentos ── */}
                    <div style={S.tableCard}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ background: '#fff3e0' }}>
                                    <th style={S.th}>Nombre del Documento</th>
                                    <th style={{ ...S.th, width: 90,  textAlign: 'center' }}>Versión</th>
                                    <th style={{ ...S.th, width: 120, textAlign: 'center' }}>Creación</th>
                                    <th style={{ ...S.th, width: 130, textAlign: 'center' }}>Caducidad</th>
                                    <th style={{ ...S.th, width: 150, textAlign: 'center' }}>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loadingDocs ? (
                                    /* Skeleton: nombre(45%), versión(10%), creación(15%), caducidad(15%), acciones(15%) */
                                    <tr><td colSpan={5} style={{ padding: 0 }}>
                                        <TableSkeleton rows={5} widths={[45, 10, 15, 15, 15]} badge />
                                    </td></tr>
                                ) : documents.length === 0 ? (
                                    <tr><td colSpan={5} style={S.emptyCell}>
                                        <i className="fa fa-search" style={{ fontSize: 32, display: 'block', marginBottom: 10, color: '#ddd' }}></i>
                                        <span style={{ fontSize: 14 }}>
                                            {activeFilterCount > 0
                                                ? 'No se encontraron documentos con los filtros aplicados'
                                                : 'No hay documentos en esta carpeta'}
                                        </span>
                                        {activeFilterCount > 0 && (
                                            <button onClick={clearFilters} style={{ marginTop: 10, padding: '6px 16px', background: '#ff8a00', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontSize: 12 }}>
                                                Limpiar filtros
                                            </button>
                                        )}
                                    </td></tr>
                                ) : documents.map((doc, i) => {
                                    const fi      = fileIcon(doc.nombre_archivo);
                                    const expired = isExpired(doc.Fecha_Caducidad);
                                    const expiring = isExpiring(doc.Fecha_Caducidad);
                                    return (
                                        <tr key={doc.idDocumento}
                                            style={{ borderBottom: '1px solid #f4f4f4', background: i % 2 === 0 ? '#fff' : '#fafafa', transition: 'background 0.15s' }}
                                            onMouseEnter={e => e.currentTarget.style.background = '#fffde7'}
                                            onMouseLeave={e => e.currentTarget.style.background = i % 2 === 0 ? '#fff' : '#fafafa'}>

                                            {/* Nombre */}
                                            <td style={{ padding: '12px 16px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                    <i className={`fa ${fi.icon}`} style={{ color: fi.color, fontSize: 18, flexShrink: 0 }}></i>
                                                    <div>
                                                        <div style={{ fontWeight: 600, color: '#333', fontSize: 13 }}>{doc.Nombre_Doc}</div>
                                                        {doc.nombre_archivo && (
                                                            <div style={{ fontSize: 11, color: '#aaa', marginTop: 1 }}>{doc.nombre_archivo}</div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Versión */}
                                            <td style={{ padding: '12px 16px', textAlign: 'center' }}>
                                                <span style={S.versionBadge}>v{doc.version || '1.0'}</span>
                                            </td>

                                            {/* Creación */}
                                            <td style={{ padding: '12px 16px', textAlign: 'center', fontSize: 12, color: '#777' }}>
                                                {doc.Fecha_creacion || '—'}
                                            </td>

                                            {/* Caducidad */}
                                            <td style={{ padding: '12px 16px', textAlign: 'center' }}>
                                                {doc.Fecha_Caducidad ? (
                                                    <span style={{
                                                        fontSize: 12, fontWeight: expired || expiring ? 700 : 400,
                                                        color: expired ? '#e53935' : expiring ? '#f57c00' : '#777',
                                                        background: expired ? '#ffebee' : expiring ? '#fff3e0' : 'transparent',
                                                        padding: expired || expiring ? '2px 7px' : '0',
                                                        borderRadius: 99,
                                                    }}>
                                                        {expired ? '⚠️ ' : expiring ? '🔔 ' : ''}{doc.Fecha_Caducidad}
                                                    </span>
                                                ) : <span style={{ fontSize: 12, color: '#bbb' }}>—</span>}
                                            </td>

                                            {/* Acciones */}
                                            <td style={{ padding: '12px 16px', textAlign: 'center' }}>
                                                <div style={{ display: 'flex', gap: 6, justifyContent: 'center' }}>
                                                    <ActionBtn icon="fa-eye"      title="Ver detalles y versiones" color="#5c6bc0" onClick={() => { setSelectedDoc(doc); setShowModal(true); }} />
                                                    <ActionBtn icon="fa-download" title="Descargar archivo"       color="#26a69a" onClick={() => handleDownload(doc.idDocumento, doc.nombre_archivo || doc.Nombre_Doc)} />
                                                    <ActionBtn icon="fa-trash"    title="Eliminar documento"      color="#e53935" onClick={() => handleDelete(doc.idDocumento)} />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {showModal && selectedDoc && (
                <DocumentDetailsModal
                    doc={selectedDoc}
                    onClose={() => { setShowModal(false); setSelectedDoc(null); fetchDocuments(); fetchFolders(); }}
                />
            )}
        </div>
    );
}

// ─── Chip de filtro activo ────────────────────────────────────────────────────

const FilterChip = ({ label, onRemove }) => (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, background: '#fff3e0', border: '1px solid #ffd18a', color: '#e65100', padding: '3px 10px', borderRadius: 99, fontSize: 11, fontWeight: 600 }}>
        {label}
        <button onClick={onRemove} style={{ background: 'none', border: 'none', color: '#e65100', cursor: 'pointer', padding: 0, fontSize: 11, lineHeight: 1, display: 'flex', alignItems: 'center' }}>
            <i className="fa fa-times"></i>
        </button>
    </span>
);

// ─── Botón de acción ──────────────────────────────────────────────────────────

const ActionBtn = ({ icon, title, color, onClick }) => (
    <button onClick={onClick} title={title}
        style={{ background: color + '14', border: 'none', borderRadius: 7, width: 32, height: 32, cursor: 'pointer', color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, transition: 'background 0.2s' }}
        onMouseEnter={e => e.currentTarget.style.background = color + '2a'}
        onMouseLeave={e => e.currentTarget.style.background = color + '14'}>
        <i className={`fa ${icon}`}></i>
    </button>
);

// ─── Estilos ──────────────────────────────────────────────────────────────────

const S = {
    root:          { display: 'flex', minHeight: '100vh', background: '#f5f6fa', fontFamily: 'Montserrat, system-ui, sans-serif' },
    mainWrapper:   { flex: 1, display: 'flex', overflow: 'hidden' },
    leftPanel:     { width: 240, background: '#fff', borderRight: '1px solid #f0f0f0', display: 'flex', flexDirection: 'column', boxShadow: '2px 0 8px rgba(0,0,0,0.04)' },
    explorerHeader:{ padding: '20px 16px 12px', borderBottom: '1px solid #f4f4f4', display: 'flex', alignItems: 'center', gap: 8 },
    folderBtn:     { width: '100%', display: 'flex', alignItems: 'center', gap: 10, padding: '10px 16px', border: 'none', cursor: 'pointer', textAlign: 'left', transition: 'all 0.15s' },
    folderIconWrap:{ width: 32, height: 32, borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 },
    uploadBox:     { padding: 14, borderTop: '1px solid #f4f4f4' },
    uploadTitle:   { fontSize: 12, fontWeight: 700, color: '#666', marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 },
    filePickerBtn: { width: '100%', padding: '8px', border: '1.5px dashed #e0e0e0', borderRadius: 8, background: '#fafafa', color: '#888', fontSize: 12, cursor: 'pointer', marginBottom: 6, boxSizing: 'border-box' },
    nameInput:     { width: '100%', border: '1px solid #e0e0e0', borderRadius: 8, padding: '6px 10px', fontSize: 12, marginBottom: 6, boxSizing: 'border-box', outline: 'none' },
    progressTrack: { height: 4, background: '#f0f0f0', borderRadius: 99, overflow: 'hidden' },
    progressFill:  { height: '100%', background: 'linear-gradient(90deg,#ff8a00,#e67300)', transition: 'width 0.3s' },
    uploadBtn:     { width: '100%', padding: '8px', border: 'none', borderRadius: 8, fontWeight: 700, fontSize: 12 },
    rightPanel:    { flex: 1, padding: 24, overflowY: 'auto' },
    tableHeader:   { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 12 },

    // Búsqueda avanzada
    searchCard:     { background: '#fff', borderRadius: 12, padding: '14px 16px', marginBottom: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.06)' },
    searchInput:    { width: '100%', paddingLeft: 34, paddingRight: 32, paddingTop: 9, paddingBottom: 9, border: '1px solid #e0e0e0', borderRadius: 8, fontSize: 13, outline: 'none', boxSizing: 'border-box' },
    clearInputBtn:  { position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#bbb', cursor: 'pointer', fontSize: 12 },
    filterToggleBtn:{ display: 'flex', alignItems: 'center', padding: '8px 14px', border: '1px solid', borderRadius: 8, fontSize: 12, fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap', transition: 'all 0.2s' },
    filterBadge:    { marginLeft: 6, background: '#ff8a00', color: '#fff', borderRadius: 99, fontSize: 10, padding: '1px 6px', fontWeight: 800 },
    selectSmall:    { padding: '8px 10px', border: '1px solid #e0e0e0', borderRadius: 8, fontSize: 12, outline: 'none', background: '#fff', color: '#555', cursor: 'pointer' },
    advancedPanel:  { marginTop: 12, paddingTop: 12, borderTop: '1px dashed #f0f0f0' },
    filtersGrid:    { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12 },
    filterGroup:    { display: 'flex', flexDirection: 'column', gap: 5 },
    filterLabel:    { fontSize: 11, fontWeight: 700, color: '#888', textTransform: 'uppercase', letterSpacing: '0.5px' },
    dateInput:      { padding: '7px 10px', border: '1px solid #e0e0e0', borderRadius: 8, fontSize: 12, outline: 'none', color: '#444' },
    clearFiltersBtn:{ padding: '7px 14px', background: '#fff', border: '1px solid #e0e0e0', borderRadius: 8, fontSize: 12, color: '#888', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', transition: 'all 0.2s' },

    // Tabla
    tableCard:  { background: '#fff', borderRadius: 12, boxShadow: '0 2px 12px rgba(0,0,0,0.07)', overflow: 'hidden' },
    th:         { padding: '11px 16px', textAlign: 'left', fontSize: 12, fontWeight: 700, color: '#e65100' },
    emptyCell:  { textAlign: 'center', padding: 48, color: '#ccc' },
    versionBadge:{ background: '#fff3e0', color: '#e65100', padding: '3px 10px', borderRadius: 99, fontSize: 12, fontWeight: 700 },
};
