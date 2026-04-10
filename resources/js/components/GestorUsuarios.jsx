// File: resources/js/components/GestorUsuarios.jsx
import React, { useState, useEffect, useMemo, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import UserTable from './UserTable';
import UserModal from './UserModal';
import Header from './Header';
import Sidebar from './Sidebar';
import TableSkeleton from './TableSkeleton';
import '../../css/Styles.css';

function StatCard({ icon, label, value, color, sub }) {
    return (
        <div style={{ background:'#fff', borderRadius:12, padding:'16px 18px', display:'flex', alignItems:'center', gap:14, boxShadow:'0 2px 8px rgba(0,0,0,.06)', borderTop:`3px solid ${color}` }}>
            <div style={{ width:44, height:44, borderRadius:11, background:color+'18', display:'flex', alignItems:'center', justifyContent:'center', fontSize:18, color, flexShrink:0 }}>
                <i className={`fa ${icon}`} />
            </div>
            <div>
                <div style={{ fontSize:22, fontWeight:900, color:'#1a1a2e', lineHeight:1 }}>{value}</div>
                <div style={{ fontSize:12, fontWeight:600, color:'#888', marginTop:3 }}>{label}</div>
                {sub && <div style={{ fontSize:11, color:'#bbb', marginTop:1 }}>{sub}</div>}
            </div>
        </div>
    );
}

export default function GestorUsuarios() {
    const [usuarios,      setUsuarios]      = useState([]);
    const [roles,         setRoles]         = useState([]);
    const [estados,       setEstados]       = useState([]);
    const [loading,       setLoading]       = useState(true);
    const [sidebarOpen,   setSidebarOpen]   = useState(() => window.innerWidth > 700);
    const [modalVisible,  setModalVisible]  = useState(false);
    const [editingUser,   setEditingUser]   = useState(null);
    const [modalType,     setModalType]     = useState('usuario');
    const [searchTerm,    setSearchTerm]    = useState('');
    const [filterBy,      setFilterBy]      = useState('all');
    const [filterEstado,  setFilterEstado]  = useState('all');
    const metaLoaded = useRef(false);

    const userName = window.currentUser || 'Administrador';

    const loadUsers = async () => {
        try {
            const res = await axios.get('/api/usuarios');
            setUsuarios(res.data);
        } catch {
            Swal.fire('Error', 'No se pudieron cargar los usuarios.', 'error');
        }
    };

    const loadAllData = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/usuarios');
            setUsuarios(res.data);
        } catch {
            Swal.fire('Error', 'Error al cargar datos.', 'error');
        } finally { setLoading(false); }
    };

    const loadMeta = async () => {
        if (metaLoaded.current) return;
        try {
            const [rR, rE] = await Promise.all([axios.get('/api/roles'), axios.get('/api/estados')]);
            setRoles(rR.data);
            setEstados(rE.data);
            metaLoaded.current = true;
        } catch { /* ignore */ }
    };

    useEffect(() => { loadAllData(); }, []);

    const stats = useMemo(() => ({
        total:    usuarios.length,
        activos:  usuarios.filter(u => u.Estado_idEstado == 1).length,
        inactivos:usuarios.filter(u => u.Estado_idEstado == 2).length,
        eliminados:usuarios.filter(u => u.Estado_idEstado == 3).length,
    }), [usuarios]);

    const filteredUsers = useMemo(() => {
        const term = searchTerm.toLowerCase();
        return usuarios.filter(u => {
            const matchSearch = !term || (
                u.Nombre_Usuario?.toLowerCase().includes(term) ||
                u.Correo?.toLowerCase().includes(term) ||
                (u.Rol||'').toLowerCase().includes(term) ||
                u.idUsuario?.toString().includes(term)
            );
            const matchEstado = filterEstado === 'all' || String(u.Estado_idEstado) === filterEstado;
            return matchSearch && matchEstado;
        });
    }, [usuarios, searchTerm, filterEstado]);

    const handleCreate = async (type = 'usuario') => {
        await loadMeta();
        setEditingUser(null);
        setModalType(type);
        setModalVisible(true);
    };

    const handleEdit = async (user, type = 'usuario') => {
        await loadMeta();
        setEditingUser(user);
        setModalType(type);
        setModalVisible(true);
    };

    const handleSave = async (formData, isNew, type = 'usuario') => {
        setModalVisible(false);
        setLoading(true);
        try {
            let url, method = isNew ? 'post' : 'put', msg;
            if (type === 'usuario') { url = isNew ? '/api/usuarios' : `/api/usuarios/${formData.idUsuario}`; msg = `Usuario ${isNew?'creado':'actualizado'}.`; }
            else if (type === 'rol') { url = isNew ? '/api/roles' : `/api/roles/${formData.idRol}`; msg = `Rol ${isNew?'creado':'actualizado'}.`; }
            else { url = isNew ? '/api/estados' : `/api/estados/${formData.idEstado}`; msg = `Estado ${isNew?'creado':'actualizado'}.`; }
            await axios({ method, url, data: formData });
            Swal.fire({ icon:'success', title:'¡Éxito!', text:msg, timer:1800, showConfirmButton:false });
            await loadUsers();
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Error al guardar.', 'error');
        } finally { setLoading(false); setEditingUser(null); }
    };

    const handleDelete = (user) => {
        Swal.fire({
            title: '¿Eliminar usuario?',
            html: `<p style="color:#666;font-size:14px">Se eliminará lógicamente a <strong>${user.Nombre_Usuario}</strong>. Podrá reactivarse desde el sistema.</p>`,
            icon:'warning', showCancelButton:true,
            confirmButtonColor:'#e53935', cancelButtonColor:'#aaa',
            confirmButtonText:'Eliminar', cancelButtonText:'Cancelar',
        }).then(async r => {
            if (!r.isConfirmed) return;
            setLoading(true);
            try {
                await axios.delete(`/api/usuarios/${user.idUsuario}`);
                Swal.fire({ icon:'success', title:'Eliminado', timer:1500, showConfirmButton:false });
                await loadUsers();
            } catch { Swal.fire('Error','No se pudo eliminar el usuario.','error'); }
            finally { setLoading(false); }
        });
    };

    return (
        <div className="gestor-app">
            {/* Backdrop móvil para sidebar */}
            {sidebarOpen && (
                <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />
            )}

            <Sidebar open={sidebarOpen} onSelect={(key, url) => {
                if (key === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (url) window.location.href = url;
            }} />

            <main className="main-content">
                <Header
                    variant="usuarios"
                    userName={userName}
                    query={searchTerm}
                    setQuery={setSearchTerm}
                    onOpenAdd={() => handleCreate('usuario')}
                    onFilterChange={setFilterBy}
                    filter={filterBy}
                    onToggleSidebar={() => setSidebarOpen(p => !p)}
                />

                <div className="content">
                    {/* ── Stats ── */}
                    <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fit,minmax(150px,1fr))', gap:14 }}>
                        <StatCard icon="fa-users"        label="Total usuarios" value={stats.total}     color="#ff8a00" />
                        <StatCard icon="fa-check-circle" label="Activos"        value={stats.activos}   color="#43a047" sub="Con acceso al sistema" />
                        <StatCard icon="fa-pause-circle" label="Inactivos"      value={stats.inactivos} color="#f57c00" />
                        <StatCard icon="fa-trash"        label="Eliminados"     value={stats.eliminados}color="#e53935" sub="Eliminación lógica" />
                    </div>

                    {/* ── Tabla de usuarios ── */}
                    <div className="table-card">
                        {/* Barra de filtros */}
                        <div style={{ padding:'14px 18px', borderBottom:'1px solid #f5f5f5', display:'flex', flexWrap:'wrap', gap:10, alignItems:'center' }}>
                            <div style={{ position:'relative', flex:'1 1 220px' }}>
                                <i className="fa fa-search" style={{ position:'absolute', left:10, top:'50%', transform:'translateY(-50%)', color:'#bbb', fontSize:13, pointerEvents:'none' }} />
                                <input
                                    type="text" value={searchTerm} onChange={e => setSearchTerm(e.target.value)}
                                    placeholder="Buscar por nombre, correo o rol..."
                                    style={{ width:'100%', padding:'9px 12px 9px 34px', border:'1.5px solid #e8eaed', borderRadius:8, fontSize:13, outline:'none', boxSizing:'border-box' }}
                                    onFocus={e=>e.target.style.borderColor='#ff8a00'} onBlur={e=>e.target.style.borderColor='#e8eaed'}
                                />
                                {searchTerm && (
                                    <button onClick={() => setSearchTerm('')} style={{ position:'absolute', right:8, top:'50%', transform:'translateY(-50%)', background:'none', border:'none', cursor:'pointer', color:'#bbb', fontSize:14 }}>
                                        <i className="fa fa-times" />
                                    </button>
                                )}
                            </div>

                            <select value={filterEstado} onChange={e => setFilterEstado(e.target.value)}
                                style={{ padding:'9px 12px', border:'1.5px solid #e8eaed', borderRadius:8, fontSize:13, background:'#fff', cursor:'pointer', flex:'0 1 160px' }}>
                                <option value="all">Todos los estados</option>
                                <option value="1">✅ Activos</option>
                                <option value="2">⚠️ Inactivos</option>
                                <option value="3">🔴 Eliminados</option>
                            </select>

                            <div style={{ display:'flex', gap:8, marginLeft:'auto' }}>
                                <button onClick={() => handleCreate('usuario')} className="btn-orange" style={{ whiteSpace:'nowrap' }}>
                                    <i className="fa fa-user-plus" /> <span className="action-label">Nuevo usuario</span>
                                </button>
                                <button onClick={() => handleCreate('rol')} className="btn-outline" title="Nuevo rol" style={{ whiteSpace:'nowrap' }}>
                                    <i className="fa fa-user-tag" /> <span className="action-label">Nuevo rol</span>
                                </button>
                                <button onClick={() => handleCreate('estado')} className="btn-outline" title="Nuevo estado" style={{ whiteSpace:'nowrap' }}>
                                    <i className="fa fa-flag" /> <span className="action-label">Nuevo estado</span>
                                </button>
                            </div>
                        </div>

                        {/* Contador */}
                        {!loading && (
                            <div style={{ padding:'8px 18px', fontSize:12, color:'#aaa', borderBottom:'1px solid #f5f5f5' }}>
                                {filteredUsers.length} usuario{filteredUsers.length !== 1 ? 's' : ''} encontrado{filteredUsers.length !== 1 ? 's' : ''}
                                {searchTerm && <span> para "<strong>{searchTerm}</strong>"</span>}
                            </div>
                        )}

                        {loading ? (
                            <TableSkeleton rows={7} widths={[35, 25, 15, 12, 13]} avatar badge />
                        ) : (
                            <UserTable rows={filteredUsers} onEdit={u => handleEdit(u, 'usuario')} onDelete={handleDelete} />
                        )}
                    </div>
                </div>
            </main>

            <UserModal visible={modalVisible} onClose={() => { setModalVisible(false); setEditingUser(null); }}
                onSave={handleSave} initial={editingUser} roles={roles} estados={estados} initialType={modalType} />
        </div>
    );
}
