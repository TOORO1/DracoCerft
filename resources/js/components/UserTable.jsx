// File: resources/js/components/UserTable.jsx
import React from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';

function getInitials(name = '') {
    return name.trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase() || 'U';
}

const AVATAR_COLORS = ['#ff8a00','#5c6bc0','#26a69a','#ef5350','#42a5f5','#ab47bc','#26c6da','#d4e157'];
function avatarColor(id) { return AVATAR_COLORS[(id || 0) % AVATAR_COLORS.length]; }

function StatusBadge({ estadoId }) {
    const map = {
        1: { label:'Activo',    bg:'#e8f5e9', color:'#2e7d32' },
        2: { label:'Inactivo',  bg:'#fff8e1', color:'#f57f17' },
        3: { label:'Eliminado', bg:'#fdecea', color:'#c62828' },
    };
    const s = map[estadoId] || { label:'Desconocido', bg:'#f5f5f5', color:'#777' };
    return (
        <span style={{ display:'inline-flex', alignItems:'center', gap:5, padding:'4px 10px', borderRadius:99, fontSize:11, fontWeight:700, background:s.bg, color:s.color }}>
            <span style={{ width:6, height:6, borderRadius:'50%', background:s.color, flexShrink:0 }} />
            {s.label}
        </span>
    );
}

function RoleBadge({ rol }) {
    const map = {
        Administrador: { bg:'#fff3e0', color:'#e65100' },
        Auditor:       { bg:'#e8f5e9', color:'#2e7d32' },
        Usuario:       { bg:'#e3f2fd', color:'#1565c0' },
    };
    const s = map[rol] || { bg:'#f3e5f5', color:'#6a1b9a' };
    return (
        <span style={{ padding:'3px 9px', borderRadius:99, fontSize:11, fontWeight:700, background:s.bg, color:s.color }}>
            {rol || '—'}
        </span>
    );
}

const pwRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

export default function UserTable({ rows = [], onEdit, onDelete }) {

    const handleChangePassword = async (row) => {
        const { value: password } = await Swal.fire({
            title: `Cambiar contraseña`,
            html: `<div style="margin-bottom:8px;font-size:13px;color:#666">Usuario: <strong>${row.Nombre_Usuario}</strong></div>`,
            input: 'password',
            inputLabel: 'Nueva contraseña',
            inputPlaceholder: 'Mínimo 8 caracteres, 1 mayúscula, 1 número, 1 especial',
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            confirmButtonColor: '#ff8a00',
            cancelButtonText: 'Cancelar',
            preConfirm: (pw) => {
                if (!pw) { Swal.showValidationMessage('La contraseña es requerida'); return; }
                if (!pwRegex.test(pw)) { Swal.showValidationMessage('Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 especial'); return; }
                return pw;
            }
        });
        if (!password) return;
        try {
            await axios.put(`/api/usuarios/${row.idUsuario}`, { Nombre_Usuario:row.Nombre_Usuario, Correo:row.Correo, Estado_idEstado:row.Estado_idEstado, password });
            Swal.fire({ icon:'success', title:'Contraseña actualizada', timer:1800, showConfirmButton:false });
            window.location.reload();
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Error al actualizar la contraseña', 'error');
        }
    };

    if (!rows.length) {
        return (
            <div style={{ padding:'50px 20px', textAlign:'center', color:'#bbb' }}>
                <i className="fa fa-users" style={{ fontSize:40, display:'block', marginBottom:14, opacity:.3 }} />
                <div style={{ fontSize:15, fontWeight:600, color:'#999', marginBottom:6 }}>No hay usuarios registrados</div>
                <div style={{ fontSize:13, color:'#bbb' }}>Crea el primer usuario con el botón "Nuevo usuario"</div>
            </div>
        );
    }

    return (
        <div className="table-wrapper" style={{ overflowX:'auto' }}>
            <table className="user-table" style={{ width:'100%', borderCollapse:'collapse', minWidth:560 }}>
                <thead>
                    <tr style={{ background:'#fafbfc', borderBottom:'2px solid #f0f0f0' }}>
                        <th style={{ padding:'12px 16px', textAlign:'left', fontSize:11, fontWeight:700, color:'#aaa', textTransform:'uppercase', letterSpacing:'.5px' }}>Usuario</th>
                        <th style={{ padding:'12px 16px', textAlign:'left', fontSize:11, fontWeight:700, color:'#aaa', textTransform:'uppercase', letterSpacing:'.5px' }}>Correo</th>
                        <th style={{ padding:'12px 16px', textAlign:'center', fontSize:11, fontWeight:700, color:'#aaa', textTransform:'uppercase', letterSpacing:'.5px' }}>Rol</th>
                        <th style={{ padding:'12px 16px', textAlign:'center', fontSize:11, fontWeight:700, color:'#aaa', textTransform:'uppercase', letterSpacing:'.5px' }}>Estado</th>
                        <th style={{ padding:'12px 16px', textAlign:'center', fontSize:11, fontWeight:700, color:'#aaa', textTransform:'uppercase', letterSpacing:'.5px' }}>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.filter(r => r?.idUsuario).map((r, i) => {
                        const isDeleted = r.Estado_idEstado == 3;
                        const color = avatarColor(r.idUsuario);
                        return (
                            <tr key={r.idUsuario}
                                style={{ borderBottom:'1px solid #f5f5f5', background:i%2===0?'#fff':'#fafafa', opacity:isDeleted?.65:1, transition:'background .1s' }}
                                onMouseEnter={e => e.currentTarget.style.background='#fffde7'}
                                onMouseLeave={e => e.currentTarget.style.background=i%2===0?'#fff':'#fafafa'}>

                                {/* Usuario + avatar */}
                                <td style={{ padding:'12px 16px' }}>
                                    <div style={{ display:'flex', alignItems:'center', gap:11 }}>
                                        <div style={{ width:36, height:36, borderRadius:10, background:color+'20', border:`2px solid ${color}40`, display:'flex', alignItems:'center', justifyContent:'center', fontSize:13, fontWeight:800, color, flexShrink:0 }}>
                                            {getInitials(r.Nombre_Usuario)}
                                        </div>
                                        <div>
                                            <div style={{ fontWeight:700, fontSize:13, color:'#333' }}>{r.Nombre_Usuario}</div>
                                            <div style={{ fontSize:11, color:'#aaa' }}>ID {r.idUsuario}{r.Cedula ? ` · ${r.Cedula}` : ''}</div>
                                        </div>
                                    </div>
                                </td>

                                <td style={{ padding:'12px 16px', fontSize:13, color:'#555' }}>{r.Correo}</td>
                                <td style={{ padding:'12px 16px', textAlign:'center' }}><RoleBadge rol={r.Rol} /></td>
                                <td style={{ padding:'12px 16px', textAlign:'center' }}><StatusBadge estadoId={r.Estado_idEstado} /></td>

                                {/* Acciones */}
                                <td style={{ padding:'10px 16px', textAlign:'center' }}>
                                    <div style={{ display:'flex', gap:6, justifyContent:'center', flexWrap:'wrap' }}>
                                        <button title="Editar usuario" onClick={() => onEdit?.(r)}
                                            style={{ padding:'6px 12px', border:'1.5px solid #e8eaed', borderRadius:7, background:'#fff', color:'#555', cursor:'pointer', fontSize:12, fontWeight:600, display:'flex', alignItems:'center', gap:5, transition:'all .15s' }}
                                            onMouseEnter={e=>{e.currentTarget.style.background='#ff8a00';e.currentTarget.style.color='#fff';e.currentTarget.style.borderColor='#ff8a00';}}
                                            onMouseLeave={e=>{e.currentTarget.style.background='#fff';e.currentTarget.style.color='#555';e.currentTarget.style.borderColor='#e8eaed';}}>
                                            <i className="fa fa-pen" /> <span className="action-label">Editar</span>
                                        </button>

                                        {!isDeleted && (<>
                                            <button title="Cambiar contraseña" onClick={() => handleChangePassword(r)}
                                                style={{ padding:'6px 10px', border:'1.5px solid #e8eaed', borderRadius:7, background:'#fff', color:'#555', cursor:'pointer', fontSize:12, display:'flex', alignItems:'center', gap:4, transition:'all .15s' }}
                                                onMouseEnter={e=>{e.currentTarget.style.background='#5c6bc0';e.currentTarget.style.color='#fff';e.currentTarget.style.borderColor='#5c6bc0';}}
                                                onMouseLeave={e=>{e.currentTarget.style.background='#fff';e.currentTarget.style.color='#555';e.currentTarget.style.borderColor='#e8eaed';}}>
                                                <i className="fa fa-key" /> <span className="action-label">Clave</span>
                                            </button>
                                            <button title="Eliminar usuario" onClick={() => onDelete?.(r)}
                                                style={{ padding:'6px 10px', border:'1.5px solid #fde', borderRadius:7, background:'#fff', color:'#e53935', cursor:'pointer', fontSize:12, display:'flex', alignItems:'center', gap:4, transition:'all .15s' }}
                                                onMouseEnter={e=>{e.currentTarget.style.background='#e53935';e.currentTarget.style.color='#fff';e.currentTarget.style.borderColor='#e53935';}}
                                                onMouseLeave={e=>{e.currentTarget.style.background='#fff';e.currentTarget.style.color='#e53935';e.currentTarget.style.borderColor='#fde';}}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </>)}
                                        {isDeleted && (
                                            <span style={{ padding:'5px 10px', background:'#fdecea', color:'#c62828', borderRadius:7, fontSize:11, fontWeight:700 }}>
                                                <i className="fa fa-lock" /> Eliminado
                                            </span>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
