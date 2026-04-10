import React, { useEffect, useState, useCallback } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import Sidebar from './Sidebar';
import Header from './Header';

const Alert = Swal.mixin({
    customClass: { container: 'swal-high-z' },
});

/* ══════════════════════════════════════════════════════════════
 |  Descripción de cada rol
 ══════════════════════════════════════════════════════════════ */
const ROL_INFO = {
    'Administrador': {
        color:   '#5c6bc0',
        bg:      '#ede7f6',
        icon:    'fa-user-shield',
        permisos: [
            'Gestión completa de usuarios (crear, editar, eliminar)',
            'Gestión de normas ISO (crear, editar, eliminar)',
            'Acceso a auditorías e informes',
            'Gestión de documentos y capacitaciones',
            'Acceso al módulo de Permisos y Roles',
        ],
    },
    'Auditor': {
        color:   '#ff8a00',
        bg:      '#fff3e0',
        icon:    'fa-clipboard-check',
        permisos: [
            'Ver y gestionar auditorías',
            'Evaluar cláusulas ISO',
            'Registrar hallazgos',
            'Ver documentos y normas',
            'Acceso de solo lectura a usuarios',
        ],
    },
    'Usuario': {
        color:   '#26a69a',
        bg:      '#e0f2f1',
        icon:    'fa-user',
        permisos: [
            'Ver y descargar documentos',
            'Acceso a capacitaciones',
            'Ver cumplimiento ISO (solo lectura)',
            'Sin acceso a gestión de usuarios ni permisos',
        ],
    },
};

/* ══════════════════════════════════════════════════════════════
 |  Badge de rol
 ══════════════════════════════════════════════════════════════ */
function RolBadge({ rol }) {
    const info = ROL_INFO[rol] || { color: '#78909c', bg: '#eceff1', icon: 'fa-user' };
    return (
        <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 5,
            background: info.bg, color: info.color,
            padding: '3px 10px', borderRadius: 20,
            fontSize: 11, fontWeight: 800,
        }}>
            <i className={`fa ${info.icon}`} />
            {rol || 'Sin Rol'}
        </span>
    );
}

/* ══════════════════════════════════════════════════════════════
 |  Componente Principal
 ══════════════════════════════════════════════════════════════ */
export default function Permisos() {
    const [sidebarOpen, setSidebarOpen] = useState(() => window.innerWidth > 700);
    const [usuarios,    setUsuarios]    = useState([]);
    const [roles,       setRoles]       = useState([]);
    const [loading,     setLoading]     = useState(true);
    const [saving,      setSaving]      = useState(null); // id del usuario guardando

    /* —— filtro —— */
    const [search, setSearch] = useState('');

    const currentUser = typeof window !== 'undefined' ? (window.currentUser || 'Administrador') : 'Administrador';

    const fetchAll = useCallback(async () => {
        setLoading(true);
        try {
            const [uRes, rRes] = await Promise.all([
                axios.get('/api/usuarios'),
                axios.get('/api/roles'),
            ]);
            setUsuarios(uRes.data || []);
            // filtrar solo los 3 roles principales
            const mainRoles = (rRes.data || []).filter(r =>
                ['Administrador', 'Auditor', 'Usuario'].includes(r.Nombre_rol)
            );
            setRoles(mainRoles.length > 0 ? mainRoles : (rRes.data || []));
        } catch (err) {
            console.error(err);
            Alert.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar la información.' });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchAll(); }, [fetchAll]);

    const handleCambiarRol = async (usuario, rolId) => {
        const nuevoRol = roles.find(r => r.idRol === parseInt(rolId))?.Nombre_rol || '';

        const result = await Alert.fire({
            icon:              'question',
            title:             '¿Cambiar rol?',
            html:              `<p>¿Asignar el rol <strong>${nuevoRol}</strong> a <strong>${usuario.Nombre_Usuario}</strong>?</p>`,
            showCancelButton:  true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText:  'Cancelar',
            confirmButtonColor: '#5c6bc0',
        });
        if (!result.isConfirmed) return;

        setSaving(usuario.idUsuario);
        try {
            const res = await axios.patch(`/api/usuarios/${usuario.idUsuario}/rol`, { rol_id: rolId });
            if (res.data.ok) {
                setUsuarios(prev => prev.map(u =>
                    u.idUsuario === usuario.idUsuario
                        ? { ...u, Rol: nuevoRol, Rol_Nombre: nuevoRol, Rol_id: parseInt(rolId) }
                        : u
                ));
                Alert.fire({ icon: 'success', title: 'Rol actualizado', text: res.data.message, timer: 2000, showConfirmButton: false });
            }
        } catch (err) {
            Alert.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'No se pudo cambiar el rol.' });
        } finally {
            setSaving(null);
        }
    };

    const filteredUsuarios = usuarios.filter(u => {
        const term = search.toLowerCase();
        return (
            (u.Nombre_Usuario || '').toLowerCase().includes(term) ||
            (u.Correo         || '').toLowerCase().includes(term) ||
            (u.Rol            || '').toLowerCase().includes(term)
        );
    });

    /* —— usuarios activos solamente —— */
    const activeUsers = filteredUsuarios.filter(u => u.Nombre_estado !== 'Eliminado');

    return (
        <div className="gestor-app">
            {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}
            <Sidebar open={sidebarOpen} onSelect={(key, url) => {
                if (key === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (url) window.location.href = url;
            }} />

            <div className="main-content">
                <Header
                    userName={currentUser}
                    onToggleSidebar={() => setSidebarOpen(p => !p)}
                />

                <div className="content">
                    {/* ── Header ──────────────────────────────────────── */}
                    <div style={{ marginBottom: 24 }}>
                        <h2 style={{ margin: 0, fontSize: 20, fontWeight: 800, color: '#333' }}>
                            <i className="fa fa-lock" style={{ color: '#ff8a00', marginRight: 10 }} />
                            Gestión de Roles y Permisos
                        </h2>
                        <p style={{ margin: '6px 0 0', color: '#888', fontSize: 13 }}>
                            Asigna roles predefinidos a los usuarios del sistema
                        </p>
                    </div>

                    {/* ── Tarjetas de roles disponibles ──────────────── */}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 16, marginBottom: 28 }}>
                        {Object.entries(ROL_INFO).map(([nombre, info]) => (
                            <div key={nombre} style={{
                                background: '#fff', borderRadius: 12, padding: 20,
                                border: `2px solid ${info.color}22`,
                                boxShadow: '0 2px 8px rgba(0,0,0,0.04)',
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 14 }}>
                                    <div style={{
                                        width: 42, height: 42, borderRadius: 10,
                                        background: info.bg, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        fontSize: 18, color: info.color, flexShrink: 0,
                                    }}>
                                        <i className={`fa ${info.icon}`} />
                                    </div>
                                    <div>
                                        <div style={{ fontWeight: 800, fontSize: 15, color: '#333' }}>{nombre}</div>
                                        <div style={{ fontSize: 11, color: '#aaa' }}>
                                            {usuarios.filter(u => u.Rol === nombre && u.Nombre_estado !== 'Eliminado').length} usuario(s)
                                        </div>
                                    </div>
                                </div>
                                <ul style={{ margin: 0, padding: '0 0 0 18px', fontSize: 12, color: '#666', lineHeight: 1.8 }}>
                                    {info.permisos.map((p, i) => (
                                        <li key={i}>{p}</li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    {/* ── Tabla de asignación ─────────────────────────── */}
                    <div className="table-card">
                        <div style={{ padding: '16px 20px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10 }}>
                            <span style={{ fontWeight: 800, fontSize: 15, color: '#333' }}>
                                <i className="fa fa-users" style={{ color: '#ff8a00', marginRight: 8 }} />
                                Asignación de Roles
                                <span style={{ background: '#fff3e0', color: '#ff8a00', borderRadius: 20, padding: '2px 10px', fontSize: 12, marginLeft: 10 }}>
                                    {activeUsers.length} usuarios
                                </span>
                            </span>
                            <input
                                type="search"
                                placeholder="Buscar usuario..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                style={{
                                    padding: '7px 12px', borderRadius: 8, border: '1.5px solid #e0e0e0',
                                    fontSize: 13, outline: 'none', width: 220,
                                }}
                                onFocus={e => e.target.style.borderColor = '#ff8a00'}
                                onBlur={e => e.target.style.borderColor = '#e0e0e0'}
                            />
                        </div>

                        {loading ? (
                            <div style={{ padding: 60, textAlign: 'center', color: '#ff8a00' }}>
                                <i className="fa fa-spinner fa-spin" style={{ fontSize: 28 }} />
                            </div>
                        ) : activeUsers.length === 0 ? (
                            <div style={{ padding: 48, textAlign: 'center', color: '#bbb' }}>
                                <i className="fa fa-users" style={{ fontSize: 36, marginBottom: 12 }} />
                                <p>No se encontraron usuarios.</p>
                            </div>
                        ) : (
                            <div className="table-wrapper">
                                <table className="user-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Correo</th>
                                            <th>Rol Actual</th>
                                            <th>Cambiar Rol</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activeUsers.map(u => (
                                            <tr key={u.idUsuario}>
                                                <td style={{ color: '#aaa', fontSize: 12 }}>{u.idUsuario}</td>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                        <div style={{
                                                            width: 32, height: 32, borderRadius: '50%',
                                                            background: '#fff3e0', color: '#ff8a00',
                                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                            fontWeight: 800, fontSize: 12, flexShrink: 0,
                                                        }}>
                                                            {(u.Nombre_Usuario || '?')[0].toUpperCase()}
                                                        </div>
                                                        <span style={{ fontWeight: 700, fontSize: 13 }}>{u.Nombre_Usuario}</span>
                                                    </div>
                                                </td>
                                                <td style={{ fontSize: 12, color: '#666' }}>{u.Correo}</td>
                                                <td><RolBadge rol={u.Rol} /></td>
                                                <td>
                                                    {saving === u.idUsuario ? (
                                                        <span style={{ color: '#ff8a00', fontSize: 12 }}>
                                                            <i className="fa fa-spinner fa-spin" style={{ marginRight: 5 }} />Guardando…
                                                        </span>
                                                    ) : (
                                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                            {roles.map(r => {
                                                                const isActive = u.Rol === r.Nombre_rol;
                                                                const info = ROL_INFO[r.Nombre_rol] || { color: '#78909c', bg: '#eceff1', icon: 'fa-user' };
                                                                return (
                                                                    <button
                                                                        key={r.idRol}
                                                                        title={`Asignar rol ${r.Nombre_rol}`}
                                                                        disabled={isActive}
                                                                        onClick={() => handleCambiarRol(u, r.idRol)}
                                                                        style={{
                                                                            padding: '4px 12px', borderRadius: 20, cursor: isActive ? 'default' : 'pointer',
                                                                            border: `1.5px solid ${isActive ? info.color : '#e0e0e0'}`,
                                                                            background: isActive ? info.bg : '#f9f9f9',
                                                                            color: isActive ? info.color : '#aaa',
                                                                            fontWeight: 700, fontSize: 11,
                                                                            transition: 'all 0.15s',
                                                                            opacity: isActive ? 1 : 0.7,
                                                                        }}
                                                                        onMouseEnter={e => { if (!isActive) { e.currentTarget.style.background = info.bg; e.currentTarget.style.color = info.color; e.currentTarget.style.borderColor = info.color; e.currentTarget.style.opacity = '1'; } }}
                                                                        onMouseLeave={e => { if (!isActive) { e.currentTarget.style.background = '#f9f9f9'; e.currentTarget.style.color = '#aaa'; e.currentTarget.style.borderColor = '#e0e0e0'; e.currentTarget.style.opacity = '0.7'; } }}
                                                                    >
                                                                        <i className={`fa ${info.icon}`} style={{ marginRight: 4 }} />
                                                                        {r.Nombre_rol}
                                                                        {isActive && <i className="fa fa-check" style={{ marginLeft: 5, fontSize: 10 }} />}
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* ── Info footer ─────────────────────────────────── */}
                    <div style={{ marginTop: 16, padding: '12px 16px', background: '#f9f9f9', borderRadius: 8, border: '1px solid #eee', fontSize: 12, color: '#888' }}>
                        <i className="fa fa-info-circle" style={{ color: '#ff8a00', marginRight: 6 }} />
                        Los roles <strong>Administrador</strong>, <strong>Auditor</strong> y <strong>Usuario</strong> están preconfigrados en el sistema.
                        Los cambios de rol se aplican de inmediato al iniciar sesión.
                    </div>
                </div>
            </div>
        </div>
    );
}
