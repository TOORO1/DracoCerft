// File: resources/js/components/Header.jsx
import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';

/* ══════════════════════════════════════════════════════════════
 |  Mapa de títulos por ruta
 ══════════════════════════════════════════════════════════════ */
const PAGE_MAP = {
    '/dashboard':            { title: 'Dashboard',           icon: 'fa-chart-bar' },
    '/gestor_usuarios':      { title: 'Gestión de Usuarios', icon: 'fa-users' },
    '/gestor_documentacion': { title: 'Gestión Documental',  icon: 'fa-file-alt' },
    '/Capacitaciones':       { title: 'Capacitaciones',      icon: 'fa-graduation-cap' },
    '/auditoria':            { title: 'Auditoría ISO',       icon: 'fa-clipboard-check' },
    '/permisos':             { title: 'Permisos',            icon: 'fa-lock' },
};

function getPageInfo() {
    const path = typeof window !== 'undefined' ? window.location.pathname : '/';
    return PAGE_MAP[path] || { title: 'DracoCert', icon: 'fa-home' };
}

function getInitials(name = '') {
    return name.trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase() || 'U';
}

/* ══════════════════════════════════════════════════════════════
 |  Componente Header
 ══════════════════════════════════════════════════════════════ */
export default function Header({
    variant         = 'default',
    userName        = '',
    query           = '',
    setQuery        = () => {},
    filter          = 'all',
    onFilterChange  = () => {},
    onOpenAdd       = null,
    onToggleSidebar = () => {},
}) {
    const [notifs,           setNotifs]           = useState({ total: 0, expiring: 0, caducados: 0, hallazgos_alta: 0, auditorias_semana: [] });
    const [showNotifPanel,   setShowNotifPanel]   = useState(false);
    const [showUserMenu,     setShowUserMenu]     = useState(false);
    const [twoFaEnabled,     setTwoFaEnabled]     = useState(false);
    const [showDisable2fa,   setShowDisable2fa]   = useState(false);
    const [disable2faPass,   setDisable2faPass]   = useState('');
    const [disable2faErr,    setDisable2faErr]    = useState('');
    const [disable2faLoading,setDisable2faLoading]= useState(false);
    const notifRef = useRef(null);
    const userRef  = useRef(null);

    const pageInfo    = getPageInfo();
    const displayName = userName || (typeof window !== 'undefined' ? window.currentUser : '') || 'Usuario';
    const initials    = getInitials(displayName);

    /* Fetch notification count + 2FA status */
    useEffect(() => {
        axios.get('/api/dashboard/notifications')
            .then(r => { if (r.data.ok) setNotifs(r.data); })
            .catch(() => {});
        axios.get('/api/2fa/status')
            .then(r => setTwoFaEnabled(r.data.enabled))
            .catch(() => {});
    }, []);

    /* Close dropdowns on outside click */
    useEffect(() => {
        const h = (e) => {
            if (notifRef.current && !notifRef.current.contains(e.target)) setShowNotifPanel(false);
            if (userRef.current  && !userRef.current.contains(e.target))  setShowUserMenu(false);
        };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    const placeholder = variant === 'usuarios'
        ? 'Buscar usuario por nombre, correo o rol…'
        : variant === 'documentacion'
            ? 'Buscar documento por nombre o tipo…'
            : 'Buscar…';

    const filterOptions = variant === 'usuarios'
        ? [{ v: 'all', l: 'Todo' }, { v: 'Nombre_Usuario', l: 'Nombre' }, { v: 'Correo', l: 'Correo' }, { v: 'Rol', l: 'Rol' }]
        : variant === 'documentacion'
            ? [{ v: 'all', l: 'Todo' }, { v: 'Nombre_Doc', l: 'Nombre' }, { v: 'Tipo', l: 'Tipo' }, { v: 'Version', l: 'Versión' }]
            : [{ v: 'all', l: 'Todo' }];

    async function handleLogout() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        try { await axios.post('/logout', {}, { headers: { 'X-CSRF-TOKEN': token } }); } catch (_) {}
        window.location.href = '/login';
    }

    async function handleDisable2fa() {
        if (!disable2faPass) return;
        setDisable2faLoading(true);
        setDisable2faErr('');
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await axios.post('/2fa/disable', { password: disable2faPass }, {
                headers: { 'X-CSRF-TOKEN': token },
            });
            setShowDisable2fa(false);
            setTwoFaEnabled(false);
            setDisable2faPass('');
            // Feedback visual breve
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'success',
                    title: '2FA desactivado',
                    text: 'La autenticación de dos factores ha sido desactivada.',
                    confirmButtonColor: '#ff8a00',
                    timer: 3000,
                    timerProgressBar: true,
                });
            }
        } catch (err) {
            const msg = err.response?.data?.errors?.password?.[0]
                     || err.response?.data?.message
                     || 'Error al desactivar 2FA. Intenta de nuevo.';
            setDisable2faErr(msg);
        } finally {
            setDisable2faLoading(false);
        }
    }

    return (
        <header className="topbar">
            <div className="topbar-inner">

                {/* ── Izquierda: toggle + breadcrumb ───────────── */}
                <div className="topbar-left">
                    <button className="topbar-toggle" onClick={onToggleSidebar} aria-label="Alternar barra lateral">
                        <i className="fa fa-bars" />
                    </button>
                    <div className="topbar-breadcrumb">
                        <span className="topbar-breadcrumb-icon">
                            <i className={`fa ${pageInfo.icon}`} />
                        </span>
                        <span className="topbar-breadcrumb-title">{pageInfo.title}</span>
                    </div>
                </div>

                {/* ── Centro: buscador ─────────────────────────── */}
                <div className="topbar-center">
                    <div className="search-pill">
                        <i className="fa fa-search" style={{ color: '#aaa', flexShrink: 0 }} />
                        <input
                            value={query}
                            onChange={e => setQuery(e.target.value)}
                            placeholder={placeholder}
                        />
                        {variant !== 'default' && (
                            <select
                                className="search-filter-select"
                                value={filter}
                                onChange={e => onFilterChange(e.target.value)}
                            >
                                {filterOptions.map(f => (
                                    <option key={f.v} value={f.v}>{f.l}</option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {/* ── Derecha: bell + user + agregar ───────────── */}
                <div className="topbar-right">

                    {/* 🔔 Notificaciones */}
                    <div className="topbar-notif-wrap" ref={notifRef}>
                        <button
                            className={`topbar-icon-btn ${notifs.total > 0 ? 'has-notif' : ''}`}
                            onClick={() => setShowNotifPanel(p => !p)}
                            title="Notificaciones"
                        >
                            <i className="fa fa-bell" />
                            {notifs.total > 0 && (
                                <span className="notif-badge">{Math.min(notifs.total, 99)}</span>
                            )}
                        </button>

                        {showNotifPanel && (
                            <div className="notif-panel">
                                <div className="notif-panel-header">
                                    <span style={{ fontWeight: 800, fontSize: 14 }}>Notificaciones</span>
                                    {notifs.total > 0 && (
                                        <span style={{
                                            background: '#ff8a00', color: '#fff',
                                            borderRadius: 20, padding: '2px 8px', fontSize: 11, fontWeight: 800,
                                        }}>
                                            {notifs.total}
                                        </span>
                                    )}
                                </div>

                                {notifs.total === 0 ? (
                                    <div className="notif-empty">
                                        <i className="fa fa-check-circle" style={{ color: '#43a047', fontSize: 32, marginBottom: 8 }} />
                                        <div style={{ fontWeight: 700, color: '#333' }}>Todo al día</div>
                                        <div style={{ fontSize: 12, color: '#aaa' }}>Sin alertas pendientes</div>
                                    </div>
                                ) : (
                                    <div>
                                        {/* Auditorías recientes */}
                                        {(notifs.auditorias_semana || []).map(a => (
                                            <a key={a.id} href="/auditoria" className="notif-item" style={{ borderLeft: '3px solid #5c6bc0' }}>
                                                <div className="notif-item-icon" style={{ background: '#ede7f6', color: '#5c6bc0' }}>
                                                    <i className="fa fa-clipboard-check" />
                                                </div>
                                                <div style={{ flex: 1 }}>
                                                    <div className="notif-item-title" style={{ color: '#5c6bc0' }}>
                                                        Auditoría: {a.titulo}
                                                    </div>
                                                    <div className="notif-item-sub">
                                                        {a.auditor ? `Auditor: ${a.auditor}` : 'Esta semana'}
                                                    </div>
                                                </div>
                                                <i className="fa fa-chevron-right" style={{ color: '#ddd', fontSize: 11 }} />
                                            </a>
                                        ))}

                                        {/* Hallazgos alta prioridad */}
                                        {notifs.hallazgos_alta > 0 && (
                                            <a href="/auditoria" className="notif-item notif-danger">
                                                <div className="notif-item-icon" style={{ background: '#fdecea', color: '#e53935' }}>
                                                    <i className="fa fa-flag" />
                                                </div>
                                                <div style={{ flex: 1 }}>
                                                    <div className="notif-item-title">
                                                        {notifs.hallazgos_alta} hallazgo{notifs.hallazgos_alta > 1 ? 's' : ''} de alta prioridad
                                                    </div>
                                                    <div className="notif-item-sub">Últimos 7 días — requieren atención</div>
                                                </div>
                                                <i className="fa fa-chevron-right" style={{ color: '#ddd', fontSize: 11 }} />
                                            </a>
                                        )}

                                        {/* Documentos por vencer */}
                                        {notifs.expiring > 0 && (
                                            <a href="/gestor_documentacion" className="notif-item notif-warning">
                                                <div className="notif-item-icon" style={{ background: '#fff8e1', color: '#f57c00' }}>
                                                    <i className="fa fa-clock" />
                                                </div>
                                                <div style={{ flex: 1 }}>
                                                    <div className="notif-item-title">
                                                        {notifs.expiring} doc{notifs.expiring > 1 ? 's' : ''} por vencer
                                                    </div>
                                                    <div className="notif-item-sub">Próximos 30 días</div>
                                                </div>
                                                <i className="fa fa-chevron-right" style={{ color: '#ddd', fontSize: 11 }} />
                                            </a>
                                        )}

                                        {/* Documentos caducados */}
                                        {notifs.caducados > 0 && (
                                            <a href="/gestor_documentacion" className="notif-item notif-danger">
                                                <div className="notif-item-icon" style={{ background: '#fdecea', color: '#e53935' }}>
                                                    <i className="fa fa-exclamation-circle" />
                                                </div>
                                                <div style={{ flex: 1 }}>
                                                    <div className="notif-item-title">
                                                        {notifs.caducados} doc{notifs.caducados > 1 ? 's' : ''} caducado{notifs.caducados > 1 ? 's' : ''}
                                                    </div>
                                                    <div className="notif-item-sub">Actualización urgente</div>
                                                </div>
                                                <i className="fa fa-chevron-right" style={{ color: '#ddd', fontSize: 11 }} />
                                            </a>
                                        )}
                                    </div>
                                )}

                                <div className="notif-panel-footer" style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <a href="/gestor_documentacion">Documentos →</a>
                                    <a href="/auditoria">Auditorías →</a>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* 👤 Avatar / menú usuario */}
                    <div className="topbar-user-wrap" ref={userRef}>
                        <button className="topbar-avatar-btn" onClick={() => setShowUserMenu(p => !p)}>
                            <div className="topbar-avatar">{initials}</div>
                            <span className="topbar-username">{displayName.split(' ')[0]}</span>
                            <i className={`fa fa-chevron-${showUserMenu ? 'up' : 'down'}`} style={{ fontSize: 10, color: '#aaa' }} />
                        </button>

                        {showUserMenu && (
                            <div className="user-menu">
                                {/* Cabecera con avatar */}
                                <div className="user-menu-header">
                                    <div className="topbar-avatar" style={{ width: 42, height: 42, fontSize: 16 }}>{initials}</div>
                                    <div>
                                        <div style={{ fontWeight: 800, fontSize: 14, color: '#333' }}>{displayName}</div>
                                        <div style={{ fontSize: 12, color: '#888' }}>
                                            {typeof window !== 'undefined' ? (window.currentRole || 'Usuario') : 'Usuario'}
                                        </div>
                                    </div>
                                </div>

                                <div style={{ height: 1, background: '#f0f0f0', margin: '4px 0' }} />

                                {/* 2FA toggle */}
                                {twoFaEnabled ? (
                                    <button
                                        className="user-menu-item"
                                        onClick={() => { setShowUserMenu(false); setShowDisable2fa(true); setDisable2faPass(''); setDisable2faErr(''); }}
                                    >
                                        <i className="fa fa-shield-halved" style={{ color: '#43a047' }} />
                                        <span style={{ flex: 1 }}>Autenticación 2FA</span>
                                        <span style={{ fontSize: 10, background: '#e8f5e9', color: '#43a047', borderRadius: 10, padding: '2px 7px', fontWeight: 700 }}>ACTIVO</span>
                                    </button>
                                ) : (
                                    <a href="/2fa/setup" className="user-menu-item" style={{ textDecoration: 'none', color: 'inherit' }}>
                                        <i className="fa fa-shield-halved" style={{ color: '#aaa' }} />
                                        <span style={{ flex: 1 }}>Activar 2FA</span>
                                        <span style={{ fontSize: 10, background: '#f5f5f5', color: '#aaa', borderRadius: 10, padding: '2px 7px', fontWeight: 700 }}>INACTIVO</span>
                                    </a>
                                )}

                                <div style={{ height: 1, background: '#f0f0f0', margin: '4px 0' }} />

                                {/* Cerrar sesión */}
                                <button className="user-menu-item" onClick={handleLogout}>
                                    <i className="fa fa-sign-out-alt" style={{ color: '#e53935' }} />
                                    <span>Cerrar Sesión</span>
                                </button>
                            </div>
                        )}

                        {/* Modal desactivar 2FA */}
                        {showDisable2fa && (
                            <div style={{
                                position: 'fixed', inset: 0, background: 'rgba(0,0,0,.45)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999,
                            }}>
                                <div style={{
                                    background: '#fff', borderRadius: 16, padding: 32, width: 340,
                                    boxShadow: '0 8px 40px rgba(0,0,0,.2)', textAlign: 'center',
                                }}>
                                    <i className="fa fa-shield-halved" style={{ fontSize: 36, color: '#ff8a00', marginBottom: 12 }} />
                                    <h5 style={{ fontWeight: 800, color: '#333', marginBottom: 6 }}>Desactivar 2FA</h5>
                                    <p style={{ fontSize: 13, color: '#888', marginBottom: 20 }}>
                                        Ingresa tu contraseña para confirmar que deseas desactivar la autenticación de dos factores.
                                    </p>
                                    {disable2faErr && (
                                        <div style={{ background: '#fdecea', color: '#c62828', borderRadius: 8, padding: '8px 12px', fontSize: 13, marginBottom: 12 }}>
                                            {disable2faErr}
                                        </div>
                                    )}
                                    <input
                                        type="password"
                                        placeholder="Tu contraseña"
                                        value={disable2faPass}
                                        onChange={e => setDisable2faPass(e.target.value)}
                                        style={{
                                            width: '100%', padding: '10px 12px', borderRadius: 8,
                                            border: '1.5px solid #ddd', fontSize: 14, marginBottom: 16,
                                            boxSizing: 'border-box',
                                        }}
                                        onKeyDown={e => e.key === 'Enter' && !disable2faLoading && handleDisable2fa()}
                                        autoFocus
                                    />
                                    <div style={{ display: 'flex', gap: 10 }}>
                                        <button
                                            onClick={() => setShowDisable2fa(false)}
                                            style={{ flex: 1, padding: '10px', borderRadius: 8, border: '1.5px solid #ddd', background: '#f5f5f5', color: '#666', fontWeight: 600, cursor: 'pointer' }}
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            onClick={handleDisable2fa}
                                            disabled={disable2faLoading || !disable2faPass}
                                            style={{ flex: 1, padding: '10px', borderRadius: 8, border: 'none', background: '#e53935', color: '#fff', fontWeight: 700, cursor: disable2faLoading ? 'wait' : 'pointer' }}
                                        >
                                            {disable2faLoading ? 'Procesando…' : 'Desactivar'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ＋ Agregar (si el módulo lo usa) */}
                    {onOpenAdd && (
                        <button className="btn-orange" onClick={onOpenAdd}>
                            <i className="fa fa-plus" />
                            <span className="topbar-add-label">Agregar</span>
                        </button>
                    )}
                </div>
            </div>
        </header>
    );
}
