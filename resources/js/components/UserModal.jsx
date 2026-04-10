// File: resources/js/components/UserModal.jsx
import React, { useEffect, useState, useMemo, useRef } from 'react';

const TYPE_META = {
    usuario: { icon: 'fa-user-circle', color: '#ff8a00', label: 'Usuario',  new: 'Nuevo usuario',    edit: 'Editar usuario' },
    rol:     { icon: 'fa-user-tag',    color: '#5c6bc0', label: 'Rol',      new: 'Nuevo rol',         edit: 'Editar rol' },
    estado:  { icon: 'fa-flag',        color: '#26a69a', label: 'Estado',   new: 'Nuevo estado',      edit: 'Editar estado' },
};

function pwStrength(pw) {
    if (!pw) return { level: 0, text: '', color: '' };
    let s = 0;
    if (pw.length >= 8) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return [
        { level:1, text:'Muy débil', color:'#e53935' },
        { level:2, text:'Débil',     color:'#f57c00' },
        { level:3, text:'Aceptable', color:'#fbc02d' },
        { level:4, text:'Fuerte',    color:'#43a047' },
    ][s - 1] || { level:0, text:'', color:'' };
}

const BASE_INPUT = {
    width:'100%', padding:'10px 12px', border:'1.5px solid #e8eaed',
    borderRadius:8, fontSize:13, outline:'none', background:'#fff',
    boxSizing:'border-box', fontFamily:'inherit', transition:'border-color .15s,box-shadow .15s',
};
const FOCUS_INPUT = { borderColor:'#ff8a00', boxShadow:'0 0 0 3px rgba(255,138,0,.1)' };
const ERR_INPUT   = { borderColor:'#e53935' };

function FInput({ error, forwardRef, ...props }) {
    const [f, setF] = useState(false);
    return <input ref={forwardRef} {...props}
        style={{ ...BASE_INPUT, ...(f ? FOCUS_INPUT : {}), ...(error ? ERR_INPUT : {}) }}
        onFocus={e=>{ setF(true);  props.onFocus?.(e); }}
        onBlur={e=>{  setF(false); props.onBlur?.(e);  }} />;
}
function FSelect({ error, children, ...props }) {
    const [f, setF] = useState(false);
    return <select {...props}
        style={{ ...BASE_INPUT, cursor:'pointer', ...(f ? FOCUS_INPUT : {}), ...(error ? ERR_INPUT : {}) }}
        onFocus={()=>setF(true)} onBlur={()=>setF(false)}>{children}</select>;
}
function FTextarea({ error, ...props }) {
    const [f, setF] = useState(false);
    return <textarea {...props}
        style={{ ...BASE_INPUT, minHeight:80, resize:'vertical', ...(f ? FOCUS_INPUT : {}), ...(error ? ERR_INPUT : {}) }}
        onFocus={()=>setF(true)} onBlur={()=>setF(false)} />;
}
function Field({ label, required, hint, error, children }) {
    return (
        <div style={{ display:'flex', flexDirection:'column', gap:5 }}>
            <label style={{ fontSize:11, fontWeight:700, color:'#888', textTransform:'uppercase', letterSpacing:'.5px' }}>
                {label}{required && <span style={{ color:'#e53935', marginLeft:2 }}>*</span>}
            </label>
            {children}
            {hint && !error && <span style={{ fontSize:11, color:'#aaa' }}>{hint}</span>}
            {error && <span style={{ fontSize:11, color:'#e53935', display:'flex', alignItems:'center', gap:4 }}>
                <i className="fa fa-exclamation-circle" /> {error}
            </span>}
        </div>
    );
}

export default function UserModal({ visible, onClose, onSave, initial, initialType='usuario', roles=[], estados=[] }) {
    const [itemType,     setItemType]     = useState(initialType);
    const [form,         setForm]         = useState({});
    const [errors,       setErrors]       = useState({});
    const [roleFilter,   setRoleFilter]   = useState('');
    const [showRoleList, setShowRoleList] = useState(false);
    const [showPw,       setShowPw]       = useState(false);
    const firstRef   = useRef(null);
    const roleDropRef = useRef(null);

    const roleOptions   = useMemo(() => (roles   || []).map(r => ({ id:r.idRol,    Nombre:r.Nombre_rol,    Desc:r.Descripcion })), [roles]);
    const estadoOptions = useMemo(() => (estados || []).map(s => ({ id:s.idEstado, Nombre:s.Nombre_estado })), [estados]);

    // cerrar dropdown al clicar fuera
    useEffect(() => {
        const h = e => { if (roleDropRef.current && !roleDropRef.current.contains(e.target)) setShowRoleList(false); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    useEffect(() => {
        if (!visible) return;
        setItemType(initialType);
        setErrors({});
        setRoleFilter('');
        setShowRoleList(false);
        setShowPw(false);
        const defEstado = estadoOptions.find(e => e.Nombre === 'Activo')?.id || estadoOptions[0]?.id || 1;
        const defRol    = roleOptions[0]?.Nombre || '';
        if (initial) {
            if (initialType === 'usuario') setForm({ idUsuario:initial.idUsuario, Cedula:initial.Cedula||'', Nombre_Usuario:initial.Nombre_Usuario||'', Correo:initial.Correo||'', Rol:initial.Rol||defRol, Estado_idEstado:initial.Estado_idEstado||defEstado, password:'' });
            else if (initialType === 'rol') setForm({ idRol:initial.idRol, Nombre_rol:initial.Nombre_rol||'', Descripcion:initial.Descripcion||'' });
            else setForm({ idEstado:initial.idEstado, Nombre_estado:initial.Nombre_estado||'', Descripcion:initial.Descripcion||'' });
        } else {
            if (initialType === 'usuario') setForm({ Cedula:'', Nombre_Usuario:'', Correo:'', Rol:defRol, Estado_idEstado:defEstado, password:'' });
            else if (initialType === 'rol') setForm({ Nombre_rol:'', Descripcion:'' });
            else setForm({ Nombre_estado:'', Descripcion:'' });
        }
        setTimeout(() => firstRef.current?.focus(), 100);
    }, [visible, initial, initialType]); // eslint-disable-line

    const validate = () => {
        const e = {};
        if (itemType === 'usuario') {
            if (!form.Nombre_Usuario?.trim()) e.Nombre_Usuario = 'El nombre es requerido';
            if (!form.Correo) e.Correo = 'El correo es requerido';
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.Correo)) e.Correo = 'Formato inválido';
            if (!initial && !form.password) e.password = 'La contraseña es requerida';
            if (form.password) {
                const f = [];
                if (form.password.length < 8)                f.push('8+ caracteres');
                if (!/[A-Z]/.test(form.password))            f.push('1 mayúscula');
                if (!/\d/.test(form.password))               f.push('1 número');
                if (!/[^A-Za-z0-9]/.test(form.password))    f.push('1 carácter especial');
                if (f.length) e.password = 'Requiere: ' + f.join(', ');
            }
            if (!form.Rol) e.Rol = 'Selecciona un rol';
        } else if (itemType === 'rol') {
            if (!form.Nombre_rol?.trim()) e.Nombre_rol = 'El nombre es requerido';
        } else {
            if (!form.Nombre_estado?.trim()) e.Nombre_estado = 'El nombre es requerido';
        }
        setErrors(e);
        return !Object.keys(e).length;
    };

    const hChange = e => {
        const { name, value } = e.target;
        setForm(p => ({ ...p, [name]:value }));
        setErrors(p => ({ ...p, [name]:undefined }));
    };

    const hSubmit = e => { e.preventDefault(); if (validate()) onSave?.(form, !initial, itemType); };

    if (!visible) return null;

    const meta     = TYPE_META[itemType];
    const pw       = pwStrength(form.password || '');
    const filtered = roleOptions.filter(r => r.Nombre.toLowerCase().includes(roleFilter.toLowerCase()));

    return (
        <div onClick={e => e.target === e.currentTarget && onClose()}
            style={{ position:'fixed', inset:0, background:'rgba(0,0,0,0.5)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:2000, backdropFilter:'blur(4px)', padding:16 }}>
            <div className="umodal-box">

                {/* Header */}
                <div className="umodal-header" style={{ background:`linear-gradient(135deg,${meta.color},${meta.color}bb)` }}>
                    <div style={{ width:44, height:44, borderRadius:12, background:'rgba(255,255,255,.2)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:20, color:'#fff', flexShrink:0 }}>
                        <i className={`fa ${meta.icon}`} />
                    </div>
                    <div style={{ flex:1 }}>
                        <div style={{ fontSize:15, fontWeight:800, color:'#fff' }}>{initial ? meta.edit : meta.new}</div>
                        <div style={{ fontSize:11, color:'rgba(255,255,255,.75)', marginTop:1 }}>
                            {itemType==='usuario' ? 'Gestión de accesos y permisos' : itemType==='rol' ? 'Define niveles de acceso' : 'Controla el estado de usuarios'}
                        </div>
                    </div>
                    {/* Tipo switcher */}
                    <div style={{ display:'flex', background:'rgba(255,255,255,.15)', borderRadius:10, padding:3, gap:2 }}>
                        {Object.entries(TYPE_META).map(([k, m]) => (
                            <button key={k} title={m.label} onClick={() => setItemType(k)} type="button"
                                style={{ width:34, height:34, border:'none', borderRadius:8, cursor:'pointer', background:itemType===k?'#fff':'transparent', color:itemType===k?m.color:'rgba(255,255,255,.7)', fontSize:14, display:'flex', alignItems:'center', justifyContent:'center', transition:'all .15s' }}>
                                <i className={`fa ${m.icon}`} />
                            </button>
                        ))}
                    </div>
                    <button onClick={onClose} type="button" style={{ width:32, height:32, border:'none', borderRadius:8, background:'rgba(255,255,255,.18)', color:'#fff', cursor:'pointer', fontSize:15, display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0, marginLeft:4 }}>
                        <i className="fa fa-times" />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={hSubmit} style={{ display:'flex', flexDirection:'column' }}>
                    <div className="umodal-body">

                        {/* ── Usuario ── */}
                        {itemType === 'usuario' && (<>
                            <div className="umodal-grid">
                                <Field label="Cédula" hint="Opcional">
                                    <FInput forwardRef={firstRef} name="Cedula" placeholder="Ej. 1234567890" value={form.Cedula||''} onChange={hChange} maxLength={50} />
                                </Field>
                                <Field label="Nombre completo" required error={errors.Nombre_Usuario}>
                                    <FInput name="Nombre_Usuario" placeholder="Nombre y apellido" value={form.Nombre_Usuario||''} onChange={hChange} error={errors.Nombre_Usuario} />
                                </Field>
                            </div>
                            <div className="umodal-grid">
                                <Field label="Correo electrónico" required error={errors.Correo}>
                                    <FInput name="Correo" type="email" placeholder="correo@empresa.com" value={form.Correo||''} onChange={hChange} error={errors.Correo} />
                                </Field>
                                <Field label="Rol" required error={errors.Rol}>
                                    <div ref={roleDropRef} style={{ position:'relative' }}>
                                        <FInput
                                            name="rol_search"
                                            placeholder="Buscar o seleccionar..."
                                            value={showRoleList ? roleFilter : (form.Rol||'')}
                                            error={errors.Rol}
                                            onChange={e=>{ setRoleFilter(e.target.value); setShowRoleList(true); }}
                                            onFocus={()=>setShowRoleList(true)}
                                            autoComplete="off"
                                        />
                                        <i className="fa fa-chevron-down" style={{ position:'absolute', right:12, top:'50%', transform:`translateY(-50%) ${showRoleList?'rotate(180deg)':''}`, color:'#bbb', fontSize:11, pointerEvents:'none', transition:'transform .2s' }} />
                                        {showRoleList && (
                                            <div style={{ position:'absolute', left:0, right:0, top:'calc(100% + 4px)', background:'#fff', border:'1.5px solid #e8eaed', borderRadius:10, boxShadow:'0 8px 24px rgba(0,0,0,.12)', zIndex:100, maxHeight:180, overflowY:'auto' }}>
                                                {!filtered.length
                                                    ? <div style={{ padding:'12px 14px', color:'#aaa', fontSize:13 }}>No se encontraron roles</div>
                                                    : filtered.map(r => (
                                                        <div key={r.id} onMouseDown={()=>{ setForm(p=>({...p,Rol:r.Nombre})); setShowRoleList(false); setRoleFilter(''); setErrors(p=>({...p,Rol:undefined})); }}
                                                            style={{ padding:'10px 14px', cursor:'pointer', fontSize:13, borderBottom:'1px solid #f5f5f5', background:form.Rol===r.Nombre?'#fff3e0':'transparent' }}
                                                            onMouseEnter={e=>e.currentTarget.style.background='#fff8f0'}
                                                            onMouseLeave={e=>e.currentTarget.style.background=form.Rol===r.Nombre?'#fff3e0':'transparent'}>
                                                            <div style={{ fontWeight:600, color:'#333' }}>
                                                                {form.Rol===r.Nombre && <i className="fa fa-check" style={{ color:'#ff8a00', marginRight:6, fontSize:11 }} />}
                                                                {r.Nombre}
                                                            </div>
                                                            {r.Desc && <div style={{ fontSize:11, color:'#aaa', marginTop:1 }}>{r.Desc}</div>}
                                                        </div>
                                                    ))
                                                }
                                            </div>
                                        )}
                                    </div>
                                </Field>
                            </div>
                            <div className="umodal-grid">
                                <Field label="Estado">
                                    <FSelect name="Estado_idEstado" value={form.Estado_idEstado||''} onChange={hChange}>
                                        {estadoOptions.map(e => <option key={e.id} value={e.id}>{e.Nombre}</option>)}
                                    </FSelect>
                                </Field>
                                <Field label={initial ? 'Nueva contraseña' : 'Contraseña'} required={!initial} hint={initial?'Dejar vacío para no cambiar':undefined} error={errors.password}>
                                    <div style={{ position:'relative' }}>
                                        <FInput name="password" type={showPw?'text':'password'} placeholder={initial?'Nueva contraseña (opcional)':'Mínimo 8 caracteres'} value={form.password||''} onChange={hChange} error={errors.password} />
                                        <button type="button" onClick={()=>setShowPw(v=>!v)} style={{ position:'absolute', right:10, top:'50%', transform:'translateY(-50%)', background:'none', border:'none', cursor:'pointer', color:'#bbb', fontSize:14 }}>
                                            <i className={`fa ${showPw?'fa-eye-slash':'fa-eye'}`} />
                                        </button>
                                    </div>
                                    {form.password && (
                                        <div style={{ marginTop:6 }}>
                                            <div style={{ display:'flex', gap:3, height:4 }}>
                                                {[1,2,3,4].map(i=>(
                                                    <div key={i} style={{ flex:1, borderRadius:99, background:i<=pw.level?pw.color:'#e8eaed', transition:'background .3s' }} />
                                                ))}
                                            </div>
                                            {pw.text && <div style={{ fontSize:10, color:pw.color, marginTop:3, fontWeight:700 }}>{pw.text}</div>}
                                        </div>
                                    )}
                                </Field>
                            </div>
                        </>)}

                        {/* ── Rol ── */}
                        {itemType === 'rol' && (<>
                            <Field label="Nombre del rol" required error={errors.Nombre_rol}>
                                <FInput forwardRef={firstRef} name="Nombre_rol" placeholder="Ej. Supervisor de calidad" value={form.Nombre_rol||''} onChange={hChange} error={errors.Nombre_rol} />
                            </Field>
                            <Field label="Descripción" hint="Describe las responsabilidades de este rol">
                                <FTextarea name="Descripcion" placeholder="¿Qué puede hacer este rol?" value={form.Descripcion||''} onChange={hChange} />
                            </Field>
                        </>)}

                        {/* ── Estado ── */}
                        {itemType === 'estado' && (<>
                            <Field label="Nombre del estado" required error={errors.Nombre_estado}>
                                <FInput forwardRef={firstRef} name="Nombre_estado" placeholder="Ej. Inactivo temporal" value={form.Nombre_estado||''} onChange={hChange} error={errors.Nombre_estado} />
                            </Field>
                            <Field label="Descripción">
                                <FTextarea name="Descripcion" placeholder="Descripción del estado" value={form.Descripcion||''} onChange={hChange} />
                            </Field>
                        </>)}
                    </div>

                    {/* Footer */}
                    <div className="umodal-footer">
                        <button type="button" className="umodal-btn-cancel" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="umodal-btn-submit" style={{ background:`linear-gradient(135deg,${meta.color},${meta.color}cc)`, boxShadow:`0 4px 14px ${meta.color}44` }}>
                            <i className={`fa ${initial?'fa-save':'fa-plus-circle'}`} />
                            {initial ? 'Guardar cambios' : `Crear ${meta.label}`}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
