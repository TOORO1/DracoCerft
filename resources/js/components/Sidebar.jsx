import React, { useState, useEffect } from 'react';
import axios from 'axios';

/* ══════════════════════════════════════════════════════════════
 |  Roles y permisos de navegación
 |  Administrador → acceso total al sistema
 |  Auditor        → Dashboard, Documentación, Auditoría, Capacitaciones
 |  Usuario        → Dashboard, Documentación, Capacitaciones
 ══════════════════════════════════════════════════════════════ */

/** Tabla de acceso por rol → lista de keys permitidos */
const ROL_ACCESO = {
    Administrador: ['dashboard', 'documentacion', 'auditoria', 'capacitaciones', 'gestor_usuarios', 'permisos', 'configuracion'],
    Auditor:       ['dashboard', 'documentacion', 'auditoria', 'capacitaciones'],
    Usuario:       ['dashboard', 'documentacion', 'capacitaciones'],
};

/** Devuelve true si el rol tiene acceso al item dado */
function rolePuede(itemKey, rol) {
    const permitidos = ROL_ACCESO[rol] ?? ROL_ACCESO['Usuario'];
    return permitidos.includes(itemKey);
}

/* Definición completa de todos los grupos y sus items */
const ALL_SIDEBAR_GROUPS = [
    {
        label: 'PRINCIPAL',
        items: [
            { key: 'dashboard', label: 'Dashboard', icon: 'fa-chart-bar', url: '/dashboard' },
        ],
    },
    {
        label: 'GESTIÓN ISO',
        items: [
            { key: 'documentacion',  label: 'Documentación',  icon: 'fa-file-alt',        url: '/gestor_documentacion' },
            { key: 'auditoria',      label: 'Auditoría',      icon: 'fa-clipboard-check', url: '/auditoria' },
            { key: 'capacitaciones', label: 'Capacitaciones', icon: 'fa-graduation-cap',  url: '/Capacitaciones' },
        ],
    },
    {
        label: 'SISTEMA',
        items: [
            { key: 'gestor_usuarios', label: 'Usuarios',          icon: 'fa-users',      url: '/gestor_usuarios' },
            { key: 'permisos',        label: 'Permisos',          icon: 'fa-lock',       url: '/permisos' },
            { key: 'configuracion',   label: 'Parametrización',   icon: 'fa-sliders-h',  url: '/configuracion' },
        ],
    },
];

/** Genera los grupos filtrados según el rol actual */
function getSidebarGroups(rol) {
    return ALL_SIDEBAR_GROUPS
        .map(group => ({ ...group, items: group.items.filter(it => rolePuede(it.key, rol)) }))
        .filter(group => group.items.length > 0);
}

/* Lista plana de todos los items (compatibilidad externa) */
export const SIDEBAR_ITEMS = ALL_SIDEBAR_GROUPS.flatMap(g => g.items);

/* Grupos completos sin filtro (compatibilidad) */
export const SIDEBAR_GROUPS = ALL_SIDEBAR_GROUPS;

/* ══════════════════════════════════════════════════════════════
 |  Helpers
 ══════════════════════════════════════════════════════════════ */
function getInitials(name = '') {
    return name.trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase() || 'U';
}

function isActive(item) {
    const p = typeof window !== 'undefined' ? window.location.pathname : '';
    return item.url && (p === item.url || p.startsWith(item.url + '/'));
}

/* ══════════════════════════════════════════════════════════════
 |  Componente principal
 ══════════════════════════════════════════════════════════════ */
export default function Sidebar({ open = true, items, onSelect }) {
    const currentUser = typeof window !== 'undefined' ? (window.currentUser || '') : '';

    // Rol: prioridad → window.currentRole (blade lo inyecta), luego fetch /api/me como respaldo
    const [currentRole, setCurrentRole] = useState(
        typeof window !== 'undefined' ? (window.currentRole || '') : ''
    );

    useEffect(() => {
        if (!currentRole) {
            axios.get('/api/me')
                .then(r => {
                    const rol = r.data?.rol ?? r.data?.role ?? 'Usuario';
                    window.currentRole = rol;
                    setCurrentRole(rol);
                })
                .catch(() => setCurrentRole('Usuario'));
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Si se pasan items externos (flat list), usarlos; de lo contrario filtrar por rol
    const groups = Array.isArray(items) && items.length
        ? [{ label: '', items }]
        : getSidebarGroups(currentRole || 'Usuario');

    async function handleLogout() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        try { await axios.post('/logout', {}, { headers: { 'X-CSRF-TOKEN': token } }); } catch (_) {}
        window.location.href = '/login';
    }

    function handleSelect(it) {
        if (onSelect) onSelect(it.key, it.url);
        else if (it.url) window.location.href = it.url;
    }

    /** Badge de color según rol */
    function roleBadgeStyle(rol) {
        const map = {
            Administrador: { bg: '#fff3e0', color: '#e65100' },
            Auditor:       { bg: '#e8f5e9', color: '#2e7d32' },
            Usuario:       { bg: '#e3f2fd', color: '#1565c0' },
        };
        return map[rol] ?? { bg: '#f5f5f5', color: '#555' };
    }

    return (
        <aside
            className={`sidebar ${open ? '' : 'collapsed'}`}
            role="navigation"
            aria-label="Barra lateral"
        >
            {/* ── Logo / Brand ──────────────────────────────── */}
            <div className="sidebar-brand">
                <div className="sidebar-logo-wrap">
                    <img src="/images/logo.png" alt="DracoCert" className="sidebar-logo" />
                </div>
                {open && (
                    <div className="sidebar-brand-text">
                        <span className="sidebar-title">DracoCert</span>
                        <span className="sidebar-subtitle">Gestión ISO</span>
                    </div>
                )}
                {/* Botón cerrar — solo visible en móvil */}
                {open && (
                    <button
                        className="sidebar-close-btn"
                        onClick={() => onSelect?.('__toggle_sidebar__', null)}
                        title="Cerrar menú"
                        aria-label="Cerrar menú"
                    >
                        <i className="fa fa-times" />
                    </button>
                )}
            </div>

            <div className="sidebar-divider" />

            {/* ── Navegación por grupos (filtrada por rol) ─── */}
            <nav className="sidebar-nav" aria-label="Navegación principal" style={{ flex: 1, overflowY: 'auto' }}>
                {groups.map((group, gi) => (
                    <div key={gi} className="sidebar-group">
                        {open && group.label && (
                            <div className="sidebar-group-label">{group.label}</div>
                        )}
                        {group.items.map(it => {
                            const active = it.active || isActive(it);
                            return (
                                <a
                                    key={it.key}
                                    href={it.url || '#'}
                                    className={`sidebar-item ${active ? 'active' : ''}`}
                                    onClick={e => { e.preventDefault(); handleSelect(it); }}
                                    aria-current={active ? 'page' : undefined}
                                    data-label={it.label}
                                    title={!open ? it.label : undefined}
                                >
                                    <span className="sidebar-item-icon">
                                        <i className={`fa ${it.icon}`} aria-hidden="true" />
                                    </span>
                                    {open && <span className="sidebar-item-label">{it.label}</span>}
                                    {open && active && (
                                        <span className="sidebar-item-dot" />
                                    )}
                                </a>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="sidebar-divider" />

            {/* ── Perfil de usuario ────────────────────────── */}
            {open ? (
                <div className="sidebar-user">
                    <div className="sidebar-user-avatar">
                        {getInitials(currentUser)}
                    </div>
                    <div className="sidebar-user-info">
                        <span className="sidebar-user-name">{currentUser || 'Usuario'}</span>
                        {/* Badge de rol con color */}
                        <span style={{
                            fontSize: 10,
                            fontWeight: 700,
                            padding: '2px 7px',
                            borderRadius: 10,
                            marginTop: 2,
                            display: 'inline-block',
                            ...roleBadgeStyle(currentRole),
                        }}>
                            {currentRole}
                        </span>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="sidebar-logout-btn"
                        title="Cerrar Sesión"
                    >
                        <i className="fa fa-sign-out-alt" />
                    </button>
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
                    <div
                        className="sidebar-user-avatar"
                        style={{ width: 36, height: 36, fontSize: 13 }}
                        title={`${currentUser} (${currentRole})`}
                    >
                        {getInitials(currentUser)}
                    </div>
                    <button
                        onClick={handleLogout}
                        className="sidebar-item"
                        title="Cerrar Sesión"
                        style={{ background: 'transparent', border: 'none', width: '100%', color: '#ef9a9a' }}
                    >
                        <span className="sidebar-item-icon">
                            <i className="fa fa-sign-out-alt" aria-hidden="true" />
                        </span>
                    </button>
                </div>
            )}

            {open && <div className="sidebar-version">DracoCert v1.0</div>}
        </aside>
    );
}
