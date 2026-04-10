import React, { useState } from 'react';
import axios from 'axios';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [statusMsg] = useState(
        typeof window !== 'undefined' ? (window.loginStatus || '') : ''
    );

    const backend = (window.backendUrl ? `${window.backendUrl.replace(/\/$/, '')}` : '');

    const submit = async (e) => {
        e.preventDefault();
        setErrors({});

        if (!email) return setErrors({ email: 'El email es obligatorio' });
        if (!password) return setErrors({ password: 'La contraseña es obligatoria' });

        setLoading(true);
        try {
            // POST directo a /login y aceptar respuesta 200 como éxito
            const res = await axios.post(`${backend}/login`, { email, password });

            if (res.status === 200) {
                // Verificar si requiere 2FA
                if (res.data?.['2fa_required']) {
                    window.location.href = '/2fa/verify';
                    return;
                }
                window.location.href = '/dashboard';
                return;
            }

            setErrors({ general: 'Credenciales inválidas' });
        } catch (err) {
            if (err.response) {
                if (err.response.status === 422) {
                    setErrors(err.response.data?.errors || { general: 'Error de validación' });
                } else if (err.response.status === 401) {
                    setErrors({ general: err.response.data?.message || 'Credenciales inválidas' });
                } else {
                    setErrors({ general: err.response.data?.error || 'Error en el servidor' });
                }
            } else {
                setErrors({ general: 'No se pudo conectar al servidor' });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="login-hero">
            <div style={{ textAlign: 'center', width: '100%', maxWidth: 900 }}>
                <h3 style={{ marginBottom: 20 }}>Bienvenido a DracoCerf</h3>
                <div className="login-card mx-auto">
                    <img src="/images/logo.png" alt="logo" className="logo" />
                    <h5>INICIO DE SESION</h5>

                    {statusMsg && (
                        <div style={{
                            background:'#e8f5e9', color:'#2e7d32', borderRadius: 8,
                            padding:'10px 14px', fontSize:13, marginBottom:16, textAlign:'left'
                        }}>
                            {statusMsg}
                        </div>
                    )}

                    {errors.general && <div className="alert-custom">{errors.general}</div>}

                    <form onSubmit={submit} noValidate>
                        <div className="mb-3 input-group">
                            <span className="input-group-text"><i className="fa fa-envelope"></i></span>
                            <input
                                type="email"
                                className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                                placeholder="Email"
                                value={email}
                                onChange={e => setEmail(e.target.value)}
                            />
                        </div>
                        {errors.email && <div className="text-danger small mb-2">{errors.email}</div>}

                        <div className="mb-3 input-group">
                            <span className="input-group-text"><i className="fa fa-lock"></i></span>
                            <input
                                type="password"
                                className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                placeholder="Contraseña"
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && !loading && submit(e)}
                                autoComplete="current-password"
                            />
                        </div>
                        {errors.password && <div className="text-danger small mb-2">{errors.password}</div>}

                        <button className="btn btn-orange w-100" type="submit" disabled={loading}>
                            {loading
                                ? <><i className="fa fa-spinner fa-spin" style={{ marginRight: 7 }} />Ingresando…</>
                                : 'INGRESAR'}
                        </button>
                    </form>

                    <a href="/password/reset" className="small-link">¿Olvido su contraseña?</a>
                </div>
            </div>
        </div>
    );
}
