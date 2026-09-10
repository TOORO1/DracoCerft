import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import Sidebar from './Sidebar.jsx';
import Header from './Header.jsx';
import TableSkeleton from './TableSkeleton.jsx';

// ─── Colores por tipo de recurso ─────────────────────────────────────────────
const TIPO_COLORS = {
    archivo:       { bg: '#e3f2fd', color: '#1565c0', icon: 'fa-file-alt' },
    formulario:    { bg: '#e8f5e9', color: '#2e7d32', icon: 'fa-clipboard-list' },
    actualizacion: { bg: '#fff3e0', color: '#e65100', icon: 'fa-sync-alt' },
};

// ─── Badge de estado según fecha de vencimiento ───────────────────────────────
function StatusBadge({ fecha }) {
    if (!fecha) return null;
    const diff = new Date(fecha) - new Date();
    const days = Math.ceil(diff / 86400000);
    if (days < 0)   return <span style={{ background:'#fdecea', color:'#c62828', padding:'2px 8px', borderRadius:99, fontSize:11, fontWeight:700 }}>Vencida</span>;
    if (days <= 30) return <span style={{ background:'#fff8e1', color:'#f57f17', padding:'2px 8px', borderRadius:99, fontSize:11, fontWeight:700 }}>Vence en {days}d</span>;
    return <span style={{ background:'#e8f5e9', color:'#2e7d32', padding:'2px 8px', borderRadius:99, fontSize:11, fontWeight:700 }}>Vigente</span>;
}

// ─── Modal para tomar la evaluación ──────────────────────────────────────────
function EvaluacionModal({ recurso, capacitacionId, puntajeAprobatorio = 60, onClose }) {
    const form      = recurso?.formulario || {};
    const preguntas = form.preguntas || [];

    const [answers,    setAnswers]    = useState({});
    const [submitted,  setSubmitted]  = useState(false);
    const [score,      setScore]      = useState(null);
    const [saving,     setSaving]     = useState(false);
    const [historial,  setHistorial]  = useState([]);
    const [loadingHist,setLoadingHist]= useState(true);
    const [tab,        setTab]        = useState('eval'); // 'eval' | 'historial'

    const pAprobatorio = puntajeAprobatorio ?? 60;

    // Cargar historial al abrir
    useEffect(() => {
        if (!capacitacionId || !recurso?.id) return;
        axios.get(`/api/capacitaciones/${capacitacionId}/recursos/${recurso.id}/resultado`)
            .then(res => {
                setHistorial(res.data.resultados || []);
            })
            .catch(() => {})
            .finally(() => setLoadingHist(false));
    }, [capacitacionId, recurso?.id]);

    const setAnswer = (idx, val) => setAnswers(prev => ({ ...prev, [idx]: val }));

    async function handleSubmit() {
        const sin = preguntas.findIndex((p, i) => p.tipo !== 'texto_libre' && answers[i] === undefined);
        if (sin !== -1) return Swal.fire('Atención', `Responde la pregunta ${sin + 1}`, 'warning');

        let correct = 0;
        preguntas.forEach((p, i) => {
            if (p.tipo === 'texto_libre') return;
            if (p.tipo === 'verdadero_falso' && String(answers[i]) === String(p.correcta)) correct++;
            if (p.tipo === 'opcion_multiple'  && Number(answers[i]) === Number(p.correcta))  correct++;
        });
        const total = preguntas.filter(p => p.tipo !== 'texto_libre').length;
        const pct   = total > 0 ? Math.round((correct / total) * 100) : 100;
        const aprobado = pct >= pAprobatorio;

        setScore({ correct, total, pct, aprobado });
        setSubmitted(true);

        // Guardar en backend
        setSaving(true);
        try {
            await axios.post(`/api/capacitaciones/${capacitacionId}/recursos/${recurso.id}/resultado`, {
                puntaje:   pct,
                correctas: correct,
                total:     total,
                respuestas: answers,
            });
            // Recargar historial
            const res = await axios.get(`/api/capacitaciones/${capacitacionId}/recursos/${recurso.id}/resultado`);
            setHistorial(res.data.resultados || []);
        } catch {
            Swal.fire('Aviso', 'El resultado no se pudo guardar en el servidor.', 'warning');
        } finally {
            setSaving(false);
        }
    }

    function reintentar() { setAnswers({}); setSubmitted(false); setScore(null); }

    const isCorrect = (p, i) => {
        if (p.tipo === 'texto_libre')    return null;
        if (p.tipo === 'verdadero_falso') return String(answers[i]) === String(p.correcta);
        return Number(answers[i]) === Number(p.correcta);
    };

    const mejorPuntaje = historial.length > 0 ? Math.max(...historial.map(r => r.puntaje)) : null;
    // ¿Ya tiene al menos un intento aprobado en el historial?
    const yaAprobado   = !loadingHist && historial.some(r => r.aprobado);

    return (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,0.55)', zIndex:3000, display:'flex', alignItems:'center', justifyContent:'center', padding:16 }}>
            <div style={{ background:'#fff', borderRadius:14, width:'100%', maxWidth:700, maxHeight:'92vh', display:'flex', flexDirection:'column', boxShadow:'0 24px 64px rgba(0,0,0,0.3)' }}>

                {/* Header */}
                <div style={{ padding:'16px 20px', borderBottom:'1px solid #f0f0f0' }}>
                    <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', marginBottom:10 }}>
                        <div>
                            <h3 style={{ margin:0, fontSize:16, fontWeight:800, color:'#1a1a2e' }}>
                                <i className="fa fa-clipboard-list" style={{ color:'#2e7d32', marginRight:8 }}></i>
                                {recurso.titulo}
                            </h3>
                            {form.instrucciones && (
                                <p style={{ margin:'4px 0 0', fontSize:12, color:'#666', lineHeight:1.5 }}>{form.instrucciones}</p>
                            )}
                        </div>
                        <button onClick={onClose} style={{ background:'none', border:'none', fontSize:20, cursor:'pointer', color:'#888', marginLeft:12, flexShrink:0 }}>
                            <i className="fa fa-times"></i>
                        </button>
                    </div>
                    {/* Tabs */}
                    <div style={{ display:'flex', gap:4 }}>
                        {[['eval','fa-clipboard-list','Evaluación'],['historial','fa-history','Mis intentos']].map(([k,ic,lbl]) => (
                            <button key={k} onClick={() => setTab(k)} style={{
                                padding:'6px 14px', border:'none', borderRadius:8, cursor:'pointer', fontSize:12, fontWeight:700,
                                background: tab===k ? '#2e7d32' : '#f0f0f0',
                                color:      tab===k ? '#fff'    : '#666',
                                transition: 'all 0.15s',
                            }}>
                                <i className={`fa ${ic}`} style={{ marginRight:5 }}></i>{lbl}
                                {k === 'historial' && historial.length > 0 && (
                                    <span style={{ marginLeft:6, background:'rgba(255,255,255,0.25)', borderRadius:99, padding:'1px 6px', fontSize:10 }}>
                                        {historial.length}
                                    </span>
                                )}
                            </button>
                        ))}
                        {/* Puntaje aprobatorio badge */}
                        <span style={{ marginLeft:'auto', background:'#e3f2fd', color:'#1565c0', borderRadius:8, padding:'5px 12px', fontSize:11, fontWeight:700, alignSelf:'center' }}>
                            <i className="fa fa-star" style={{ marginRight:4 }}></i>Aprobatorio: {pAprobatorio}%
                        </span>
                        {mejorPuntaje !== null && (
                            <span style={{ background: mejorPuntaje >= pAprobatorio ? '#e8f5e9':'#fdecea', color: mejorPuntaje >= pAprobatorio ? '#2e7d32':'#c62828', borderRadius:8, padding:'5px 12px', fontSize:11, fontWeight:700, alignSelf:'center' }}>
                                <i className="fa fa-trophy" style={{ marginRight:4 }}></i>Mejor: {mejorPuntaje}%
                            </span>
                        )}
                    </div>
                </div>

                {/* TAB: EVALUACIÓN */}
                {tab === 'eval' && (<>
                    {/* Banner: ya aprobado previamente */}
                    {yaAprobado && !submitted && (
                        <div style={{ padding:'20px', background:'#e8f5e9', borderBottom:'1px solid #c8e6c9', textAlign:'center' }}>
                            <div style={{ fontSize:32, color:'#2e7d32', marginBottom:6 }}>
                                <i className="fa fa-check-circle"></i>
                            </div>
                            <div style={{ fontWeight:800, fontSize:15, color:'#2e7d32' }}>¡Ya aprobaste esta evaluación!</div>
                            <div style={{ fontSize:13, color:'#555', marginTop:4 }}>
                                Mejor puntaje obtenido: <strong>{mejorPuntaje}%</strong> — mínimo requerido: <strong>{pAprobatorio}%</strong>
                            </div>
                            <button onClick={() => setTab('historial')} style={{ marginTop:10, padding:'7px 16px', borderRadius:8, border:'none', background:'#2e7d32', color:'#fff', cursor:'pointer', fontSize:13, fontWeight:700 }}>
                                <i className="fa fa-history" style={{ marginRight:5 }}></i>Ver mis intentos
                            </button>
                        </div>
                    )}

                    {/* Resultado inmediato */}
                    {submitted && score && (
                        <div style={{ padding:'12px 20px', background: score.aprobado ? '#e8f5e9' : '#fdecea', borderBottom:'1px solid #f0f0f0', textAlign:'center' }}>
                            <div style={{ fontSize:38, fontWeight:900, color: score.aprobado ? '#2e7d32' : '#c62828', lineHeight:1 }}>{score.pct}%</div>
                            <div style={{ fontSize:13, color:'#555', marginTop:4 }}>{score.correct} de {score.total} correctas</div>
                            <div style={{ fontSize:13, fontWeight:700, marginTop:2, color: score.aprobado ? '#2e7d32' : '#c62828' }}>
                                {score.pct >= 90 ? '¡Excelente!' : score.aprobado ? '¡Aprobado ✓' : `No aprobado — necesitas ${pAprobatorio}% mínimo`}
                            </div>
                            {saving && <div style={{ fontSize:11, color:'#888', marginTop:4 }}><i className="fa fa-spinner fa-spin"></i> Guardando resultado…</div>}
                            {!saving && <div style={{ fontSize:11, color:'#aaa', marginTop:4 }}><i className="fa fa-check-circle"></i> Resultado guardado</div>}
                        </div>
                    )}

                    {/* Preguntas */}
                    <div style={{ flex:1, overflowY:'auto', padding:'16px 20px' }}>
                        {preguntas.length === 0 && (
                            <div style={{ textAlign:'center', color:'#bbb', padding:48 }}>
                                <i className="fa fa-clipboard" style={{ fontSize:36, display:'block', marginBottom:12 }}></i>
                                Este formulario aún no tiene preguntas.
                            </div>
                        )}
                        {preguntas.map((p, i) => {
                            const ok = submitted ? isCorrect(p, i) : null;
                            const border = submitted && p.tipo !== 'texto_libre' ? (ok ? '#4caf50' : '#f44336') : '#e8e8e8';
                            const bgCard = submitted && p.tipo !== 'texto_libre' ? (ok ? '#f1f8e9' : '#fff5f5') : '#fafbfc';
                            return (
                                <div key={i} style={{ marginBottom:16, padding:16, borderRadius:10, border:`2px solid ${border}`, background:bgCard }}>
                                    <div style={{ fontWeight:700, fontSize:14, marginBottom:10, color:'#1a1a2e', display:'flex', alignItems:'center', gap:8 }}>
                                        <span style={{ background:'#ff8a00', color:'#fff', borderRadius:99, width:22, height:22, display:'inline-flex', alignItems:'center', justifyContent:'center', fontSize:11, flexShrink:0 }}>{i+1}</span>
                                        {p.texto}
                                        {submitted && p.tipo !== 'texto_libre' && (
                                            <span style={{ marginLeft:'auto', fontSize:12, fontWeight:700, color: ok ? '#2e7d32' : '#c62828', flexShrink:0 }}>
                                                <i className={`fa ${ok ? 'fa-check-circle' : 'fa-times-circle'}`}></i> {ok ? 'Correcto' : 'Incorrecto'}
                                            </span>
                                        )}
                                    </div>

                                    {p.tipo === 'opcion_multiple' && (
                                        <div style={{ display:'flex', flexDirection:'column', gap:6 }}>
                                            {(p.opciones || []).map((op, oi) => {
                                                const sel  = Number(answers[i]) === oi;
                                                const good = submitted && Number(p.correcta) === oi;
                                                const bad  = submitted && sel && !good;
                                                return (
                                                    <label key={oi} style={{ display:'flex', alignItems:'center', gap:10, padding:'9px 12px', borderRadius:8, cursor: submitted ? 'default':'pointer', background: good ? '#e8f5e9' : bad ? '#fdecea' : sel ? '#e3f2fd' : '#fff', border:`1px solid ${good ? '#4caf50' : bad ? '#f44336' : '#e0e0e0'}`, transition:'all 0.15s' }}>
                                                        <input type="radio" name={`q_${i}`} value={oi} checked={sel} onChange={() => !submitted && setAnswer(i, oi)} disabled={submitted} style={{ accentColor:'#ff8a00' }} />
                                                        <span style={{ fontSize:13 }}>{op}</span>
                                                        {good && <i className="fa fa-check" style={{ marginLeft:'auto', color:'#2e7d32', fontSize:12 }}></i>}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {p.tipo === 'verdadero_falso' && (
                                        <div style={{ display:'flex', gap:10 }}>
                                            {[['true','Verdadero','fa-check'],['false','Falso','fa-times']].map(([val, label, ico]) => {
                                                const sel  = String(answers[i]) === val;
                                                const good = submitted && String(p.correcta) === val;
                                                const bad  = submitted && sel && !good;
                                                return (
                                                    <label key={val} style={{ flex:1, display:'flex', alignItems:'center', gap:10, padding:'10px 14px', borderRadius:8, cursor: submitted ? 'default':'pointer', background: good ? '#e8f5e9' : bad ? '#fdecea' : sel ? '#e3f2fd' : '#fff', border:`1px solid ${good ? '#4caf50' : bad ? '#f44336' : '#e0e0e0'}`, fontWeight:600, fontSize:13, transition:'all 0.15s' }}>
                                                        <input type="radio" name={`q_${i}`} value={val} checked={sel} onChange={() => !submitted && setAnswer(i, val)} disabled={submitted} style={{ accentColor:'#ff8a00' }} />
                                                        <i className={`fa ${ico}`} style={{ color: val === 'true' ? '#2e7d32':'#c62828', fontSize:12 }}></i>
                                                        {label}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {p.tipo === 'texto_libre' && (
                                        <textarea rows={3} value={answers[i] || ''} onChange={e => setAnswer(i, e.target.value)} disabled={submitted}
                                            placeholder="Escribe tu respuesta aquí..."
                                            style={{ width:'100%', borderRadius:8, border:'1px solid #e0e0e0', padding:'8px 12px', fontSize:13, resize:'vertical', boxSizing:'border-box', fontFamily:'inherit' }} />
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Footer */}
                    <div style={{ padding:'12px 20px', borderTop:'1px solid #f0f0f0', display:'flex', justifyContent:'flex-end', gap:8 }}>
                        <button onClick={onClose} style={{ padding:'8px 16px', borderRadius:8, border:'1px solid #ddd', background:'#fff', cursor:'pointer', fontSize:13 }}>Cerrar</button>
                        {/* Solo mostrar "Enviar" si no está aprobado (ni ahora ni antes) y no se ha enviado aún */}
                        {!submitted && !yaAprobado && preguntas.length > 0 && (
                            <button onClick={handleSubmit} style={{ padding:'8px 18px', borderRadius:8, border:'none', background:'#2e7d32', color:'#fff', cursor:'pointer', fontSize:13, fontWeight:700 }}>
                                <i className="fa fa-paper-plane" style={{ marginRight:6 }}></i>Enviar respuestas
                            </button>
                        )}
                        {/* Reintentar solo si el intento actual NO aprobó */}
                        {submitted && !score?.aprobado && (
                            <button onClick={reintentar} style={{ padding:'8px 18px', borderRadius:8, border:'none', background:'#ff8a00', color:'#fff', cursor:'pointer', fontSize:13, fontWeight:700 }}>
                                <i className="fa fa-redo" style={{ marginRight:6 }}></i>Reintentar
                            </button>
                        )}
                    </div>
                </>)}

                {/* TAB: HISTORIAL */}
                {tab === 'historial' && (
                    <div style={{ flex:1, overflowY:'auto', padding:'16px 20px' }}>
                        {loadingHist ? (
                            <div style={{ textAlign:'center', padding:48, color:'#aaa' }}>
                                <i className="fa fa-spinner fa-spin" style={{ fontSize:24 }}></i>
                            </div>
                        ) : historial.length === 0 ? (
                            <div style={{ textAlign:'center', padding:48, color:'#bbb' }}>
                                <i className="fa fa-history" style={{ fontSize:36, display:'block', marginBottom:12 }}></i>
                                Aún no has realizado esta evaluación.<br />
                                <button onClick={() => setTab('eval')} style={{ marginTop:12, padding:'8px 18px', borderRadius:8, border:'none', background:'#2e7d32', color:'#fff', cursor:'pointer', fontSize:13, fontWeight:700 }}>
                                    Comenzar evaluación
                                </button>
                            </div>
                        ) : (
                            <table style={{ width:'100%', borderCollapse:'collapse', fontSize:13 }}>
                                <thead>
                                    <tr style={{ background:'#f9f9f9' }}>
                                        <th style={{ padding:'10px 12px', textAlign:'left', fontWeight:700, color:'#555', fontSize:11, textTransform:'uppercase' }}>Intento</th>
                                        <th style={{ padding:'10px 12px', textAlign:'center', fontWeight:700, color:'#555', fontSize:11, textTransform:'uppercase' }}>Puntaje</th>
                                        <th style={{ padding:'10px 12px', textAlign:'center', fontWeight:700, color:'#555', fontSize:11, textTransform:'uppercase' }}>Correctas</th>
                                        <th style={{ padding:'10px 12px', textAlign:'center', fontWeight:700, color:'#555', fontSize:11, textTransform:'uppercase' }}>Estado</th>
                                        <th style={{ padding:'10px 12px', textAlign:'left', fontWeight:700, color:'#555', fontSize:11, textTransform:'uppercase' }}>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {historial.map((r, idx) => (
                                        <tr key={r.id} style={{ borderBottom:'1px solid #f0f0f0' }}>
                                            <td style={{ padding:'10px 12px', color:'#aaa' }}>#{historial.length - idx}</td>
                                            <td style={{ padding:'10px 12px', textAlign:'center', fontWeight:900, fontSize:16, color: r.aprobado ? '#2e7d32':'#c62828' }}>
                                                {r.puntaje}%
                                            </td>
                                            <td style={{ padding:'10px 12px', textAlign:'center', color:'#555' }}>
                                                {r.correctas} / {r.total}
                                            </td>
                                            <td style={{ padding:'10px 12px', textAlign:'center' }}>
                                                <span style={{ background: r.aprobado ? '#e8f5e9':'#fdecea', color: r.aprobado ? '#2e7d32':'#c62828', padding:'3px 10px', borderRadius:99, fontSize:11, fontWeight:700 }}>
                                                    <i className={`fa ${r.aprobado ? 'fa-check-circle':'fa-times-circle'}`} style={{ marginRight:4 }}></i>
                                                    {r.aprobado ? 'Aprobado' : 'No aprobado'}
                                                </span>
                                            </td>
                                            <td style={{ padding:'10px 12px', color:'#888', fontSize:12 }}>
                                                {r.fecha ? new Date(r.fecha).toLocaleString('es-CO', { dateStyle:'short', timeStyle:'short' }) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        <div style={{ padding:'12px 0 0', display:'flex', justifyContent:'flex-end' }}>
                            <button onClick={onClose} style={{ padding:'8px 16px', borderRadius:8, border:'1px solid #ddd', background:'#fff', cursor:'pointer', fontSize:13 }}>Cerrar</button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Componente principal ─────────────────────────────────────────────────────
export default function Capacitaciones() {
    const [items,        setItems]        = useState([]);
    const [recursos,     setRecursos]     = useState([]);
    const [sidebarOpen,  setSidebarOpen]  = useState(() => window.innerWidth > 700);
    const [query,        setQuery]        = useState('');
    const [selectedCap,  setSelectedCap]  = useState(null);
    const [loading,      setLoading]      = useState(false);
    const [loadingList,  setLoadingList]  = useState(true);   // skeleton inicial de la lista
    const [showForm,     setShowForm]     = useState(false);
    const [showRecForm,  setShowRecForm]  = useState(false);
    const [showEvalModal,setShowEvalModal] = useState(false);
    const [evalRecurso,  setEvalRecurso]  = useState(null);
    const [resultadosMap,setResultadosMap] = useState({}); // { recurso_id: { mejor_puntaje, aprobado, intentos } }

    // Form nueva capacitación
    const [form, setForm] = useState({ Nombre_curso:'', Fecha_Vencimiento:'', Fecha_Realizacion:'', Puntaje:'' });
    const fileCapRef = useRef();

    // Form nuevo recurso
    const [recForm, setRecForm] = useState({ tipo:'archivo', titulo:'', descripcion:'' });
    const fileRecRef = useRef();

    // Builder de formulario de evaluación
    const [instrucciones,  setInstrucciones]  = useState('');
    const [preguntasForm,  setPreguntasForm]  = useState([]);

    useEffect(() => { fetchList(true); }, []);
    useEffect(() => { fetchRecursos(selectedCap); }, [selectedCap]);

    // ── Reset builder cuando cambia el tipo de recurso ───────────────────────
    useEffect(() => {
        if (recForm.tipo !== 'formulario') {
            setPreguntasForm([]);
            setInstrucciones('');
        }
    }, [recForm.tipo]);

    // ── API ──────────────────────────────────────────────────────────────────
    async function fetchList(isInitial = false) {
        if (isInitial) setLoadingList(true);
        try {
            const res = await axios.get('/api/capacitaciones', { params: { q: query || undefined } });
            const data = res.data || [];
            setItems(data);
            if (data.length && !selectedCap) setSelectedCap(data[0].idCapacitacion);
        } catch (e) {
            Swal.fire('Error', 'No se pudo cargar las capacitaciones', 'error');
        } finally {
            if (isInitial) setLoadingList(false);
        }
    }

    async function fetchRecursos(capId) {
        if (!capId) { setRecursos([]); setResultadosMap({}); return; }
        try {
            const [resR, resM] = await Promise.all([
                axios.get(`/api/capacitaciones/${capId}/recursos`),
                axios.get(`/api/capacitaciones/${capId}/mis-resultados`),
            ]);
            setRecursos(resR.data || []);
            setResultadosMap(resM.data || {});
        } catch { setRecursos([]); setResultadosMap({}); }
    }

    async function refreshResultados(capId) {
        if (!capId) return;
        try {
            const res = await axios.get(`/api/capacitaciones/${capId}/mis-resultados`);
            setResultadosMap(res.data || {});
        } catch { /* ignore */ }
    }

    // ── CRUD capacitación ────────────────────────────────────────────────────
    async function handleSubmitCapacitacion(e) {
        e.preventDefault();
        if (!form.Nombre_curso.trim()) return Swal.fire('Atención', 'Ingresa el nombre del curso', 'warning');
        const fd = new FormData();
        Object.entries(form).forEach(([k,v]) => { if (v) fd.append(k, v); });
        if (fileCapRef.current?.files?.[0]) fd.append('archivo', fileCapRef.current.files[0]);
        setLoading(true);
        try {
            const res = await axios.post('/api/capacitaciones', fd, { headers:{'Content-Type':'multipart/form-data'} });
            Swal.fire({ icon:'success', title:'Capacitación guardada', timer:1400, showConfirmButton:false });
            setForm({ Nombre_curso:'', Fecha_Vencimiento:'', Fecha_Realizacion:'', Puntaje:'' });
            if (fileCapRef.current) fileCapRef.current.value = '';
            setShowForm(false);
            await fetchList();
            const newId = res.data?.idCapacitacion;
            if (newId) setSelectedCap(newId);
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Error creando capacitación', 'error');
        } finally { setLoading(false); }
    }

    async function handleDelete(id) {
        const r = await Swal.fire({ title:'¿Eliminar capacitación?', text:'Esta acción no se puede deshacer.', icon:'warning', showCancelButton:true, confirmButtonColor:'#e53935', confirmButtonText:'Eliminar', cancelButtonText:'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            await axios.delete(`/api/capacitaciones/${id}`);
            Swal.fire({ icon:'success', title:'Eliminado', timer:1200, showConfirmButton:false });
            setSelectedCap(null); setRecursos([]);
            await fetchList();
        } catch { Swal.fire('Error', 'No se pudo eliminar', 'error'); }
    }

    // ── Helpers del form builder ─────────────────────────────────────────────
    function addPregunta() {
        setPreguntasForm(prev => [...prev, { id: Date.now(), texto:'', tipo:'opcion_multiple', opciones:['',''], correcta:0 }]);
    }

    function updatePregunta(idx, field, value) {
        setPreguntasForm(prev => prev.map((p, i) => i === idx ? { ...p, [field]: value } : p));
    }

    function addOpcion(idx) {
        setPreguntasForm(prev => prev.map((p, i) => i === idx ? { ...p, opciones:[...p.opciones, ''] } : p));
    }

    function updateOpcion(pIdx, oIdx, val) {
        setPreguntasForm(prev => prev.map((p, i) => i === pIdx ? { ...p, opciones: p.opciones.map((o, oi) => oi === oIdx ? val : o) } : p));
    }

    function removeOpcion(pIdx, oIdx) {
        setPreguntasForm(prev => prev.map((p, i) => {
            if (i !== pIdx) return p;
            const ops = p.opciones.filter((_, oi) => oi !== oIdx);
            return { ...p, opciones: ops, correcta: Math.min(p.correcta, Math.max(0, ops.length - 1)) };
        }));
    }

    function removePregunta(idx) {
        setPreguntasForm(prev => prev.filter((_, i) => i !== idx));
    }

    // ── CRUD recurso ─────────────────────────────────────────────────────────
    async function handleAddResource(e) {
        e.preventDefault();
        if (!selectedCap) return Swal.fire('Atención', 'Selecciona una capacitación', 'warning');
        if (!recForm.titulo.trim()) return Swal.fire('Atención', 'Ingresa un título', 'warning');

        const fd = new FormData();
        fd.append('tipo', recForm.tipo);
        fd.append('titulo', recForm.titulo);
        fd.append('descripcion', recForm.descripcion);

        if (recForm.tipo === 'formulario') {
            if (!preguntasForm.length) return Swal.fire('Atención', 'Agrega al menos una pregunta al formulario', 'warning');
            const sinTexto = preguntasForm.findIndex(p => !p.texto.trim());
            if (sinTexto !== -1) return Swal.fire('Atención', `La pregunta ${sinTexto + 1} no tiene texto`, 'warning');
            const sinOpciones = preguntasForm.find(p => p.tipo === 'opcion_multiple' && p.opciones.filter(o => o.trim()).length < 2);
            if (sinOpciones) return Swal.fire('Atención', 'Las preguntas de opción múltiple necesitan al menos 2 opciones', 'warning');
            fd.append('formulario_data', JSON.stringify({
                instrucciones: instrucciones.trim(),
                preguntas: preguntasForm.map(p => ({
                    texto:   p.texto,
                    tipo:    p.tipo,
                    ...(p.tipo === 'opcion_multiple'  ? { opciones: p.opciones.filter(o => o.trim()), correcta: p.correcta } : {}),
                    ...(p.tipo === 'verdadero_falso'  ? { correcta: p.correcta } : {}),
                })),
            }));
        } else {
            if (recForm.tipo === 'archivo' && !fileRecRef.current?.files?.[0]) return Swal.fire('Atención', 'Adjunta un archivo', 'warning');
            if (fileRecRef.current?.files?.[0]) fd.append('archivo', fileRecRef.current.files[0]);
        }

        setLoading(true);
        try {
            await axios.post(`/api/capacitaciones/${selectedCap}/recursos`, fd, { headers:{'Content-Type':'multipart/form-data'} });
            Swal.fire({ icon:'success', title:'Recurso agregado', timer:1200, showConfirmButton:false });
            setRecForm({ tipo:'archivo', titulo:'', descripcion:'' });
            setPreguntasForm([]); setInstrucciones('');
            if (fileRecRef.current) fileRecRef.current.value = '';
            setShowRecForm(false);
            await fetchRecursos(selectedCap);
        } catch (err) {
            Swal.fire('Error', err.response?.data?.error || 'Error creando recurso', 'error');
        } finally { setLoading(false); }
    }

    async function handleDeleteResource(id) {
        const r = await Swal.fire({ title:'¿Eliminar recurso?', icon:'warning', showCancelButton:true, confirmButtonColor:'#e53935', confirmButtonText:'Eliminar', cancelButtonText:'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            await axios.delete(`/api/capacitaciones/${selectedCap}/recursos/${id}`);
            await fetchRecursos(selectedCap);
        } catch { Swal.fire('Error', 'No se pudo eliminar', 'error'); }
    }

    function handleDownloadCap(cap) {
        const a = document.createElement('a');
        a.href = `/api/capacitaciones/${cap.idCapacitacion}/descargar`;
        a.target = '_blank';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    }

    function handleDownloadRecurso(r) {
        const a = document.createElement('a');
        a.href = `/api/capacitaciones/${selectedCap}/recursos/${r.id}/descargar`;
        a.target = '_blank';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    }

    const selectedItem = items.find(i => i.idCapacitacion === selectedCap);

    // ── Render ───────────────────────────────────────────────────────────────
    return (
        <div className="gestor-app">
            {showEvalModal && evalRecurso && (
                <EvaluacionModal
                    recurso={evalRecurso}
                    capacitacionId={selectedCap}
                    puntajeAprobatorio={selectedItem?.Puntaje ?? 60}
                    onClose={() => {
                        setShowEvalModal(false);
                        setEvalRecurso(null);
                        refreshResultados(selectedCap);
                    }}
                />
            )}

            {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}
            <Sidebar open={sidebarOpen} onSelect={(k,u) => {
                if (k === '__toggle_sidebar__') { setSidebarOpen(p => !p); return; }
                if (u) window.location.href = u;
            }} />
            <div className="main-content">
                <Header variant="default" userName={window.currentUser || ''} onToggleSidebar={() => setSidebarOpen(v => !v)} onOpenAdd={() => setShowForm(true)} />
                <div className="content">
                    <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:4 }}>
                        <div>
                            <h2 style={{ margin:0, fontSize:20, fontWeight:800, color:'#1a1a2e' }}>Capacitaciones</h2>
                            <p style={{ color:'#888', fontSize:13, margin:'4px 0 0' }}>Gestión de cursos, recursos y evaluaciones</p>
                        </div>
                        <button className="btn-orange" onClick={() => setShowForm(v => !v)}>
                            <i className="fa fa-plus"></i> Nueva Capacitación
                        </button>
                    </div>

                    {/* ── MODAL NUEVA CAPACITACIÓN ── */}
                    {showForm && (
                        <div onClick={e => e.target === e.currentTarget && setShowForm(false)} style={{
                            position:'fixed', inset:0, background:'rgba(0,0,0,0.45)', zIndex:2000,
                            display:'flex', alignItems:'center', justifyContent:'center',
                            backdropFilter:'blur(3px)', padding:16,
                        }}>
                            <div style={{
                                width:'min(560px, 96%)', background:'#fff', borderRadius:16,
                                boxShadow:'0 24px 64px rgba(0,0,0,0.22)', overflow:'hidden',
                                display:'flex', flexDirection:'column',
                            }}>
                                {/* Header */}
                                <div style={{ background:'linear-gradient(135deg,#ff8a00,#e67300)', padding:'18px 20px', display:'flex', alignItems:'center', gap:14 }}>
                                    <div style={{ width:44, height:44, borderRadius:12, background:'rgba(255,255,255,0.2)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:20, color:'#fff' }}>
                                        <i className="fa fa-graduation-cap" />
                                    </div>
                                    <div style={{ flex:1 }}>
                                        <div style={{ fontSize:16, fontWeight:800, color:'#fff' }}>Nueva Capacitación</div>
                                        <div style={{ fontSize:12, color:'rgba(255,255,255,0.75)', marginTop:2 }}>Registra un nuevo curso o programa de formación</div>
                                    </div>
                                    <button onClick={() => setShowForm(false)} style={{ width:32, height:32, border:'none', borderRadius:8, background:'rgba(255,255,255,0.18)', color:'#fff', cursor:'pointer', fontSize:15, display:'flex', alignItems:'center', justifyContent:'center' }}>
                                        <i className="fa fa-times" />
                                    </button>
                                </div>

                                {/* Body */}
                                <form onSubmit={handleSubmitCapacitacion}>
                                    <div style={{ padding:'20px 20px 8px', display:'flex', flexDirection:'column', gap:14, maxHeight:'65vh', overflowY:'auto' }}>
                                        <div className="field-block">
                                            <label>Nombre del Curso <span style={{ color:'#e53935' }}>*</span></label>
                                            <input className="input-text" value={form.Nombre_curso} onChange={e => setForm({ ...form, Nombre_curso:e.target.value })} placeholder="Ej. Gestión de Calidad ISO 9001" autoFocus />
                                        </div>
                                        <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fit,minmax(200px,1fr))', gap:14 }}>
                                            <div className="field-block">
                                                <label>Fecha de Realización</label>
                                                <input className="input-text" type="date" value={form.Fecha_Realizacion} onChange={e => setForm({ ...form, Fecha_Realizacion:e.target.value })} />
                                            </div>
                                            <div className="field-block">
                                                <label>Fecha de Vencimiento</label>
                                                <input className="input-text" type="date" value={form.Fecha_Vencimiento} onChange={e => setForm({ ...form, Fecha_Vencimiento:e.target.value })} />
                                            </div>
                                        </div>
                                        <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fit,minmax(200px,1fr))', gap:14 }}>
                                            <div className="field-block">
                                                <label>Puntaje aprobatorio <span style={{ color:'#aaa', fontWeight:'normal' }}>(0–100)</span></label>
                                                <input className="input-text" type="number" min="0" max="100" value={form.Puntaje} onChange={e => setForm({ ...form, Puntaje:e.target.value })} placeholder="Ej. 70" />
                                            </div>
                                            <div className="field-block">
                                                <label>Certificado / Diploma <span style={{ color:'#aaa', fontWeight:'normal' }}>(opcional)</span></label>
                                                <input type="file" ref={fileCapRef} className="input-text" style={{ padding:'6px 12px' }} />
                                            </div>
                                        </div>
                                    </div>
                                    {/* Footer */}
                                    <div style={{ padding:'14px 20px', borderTop:'1px solid #f0f0f0', display:'flex', justifyContent:'flex-end', gap:10, background:'#fafbfc' }}>
                                        <button type="button" className="btn-outline" onClick={() => setShowForm(false)}>Cancelar</button>
                                        <button type="submit" className="btn-orange" disabled={loading}>
                                            {loading ? <><i className="fa fa-spinner fa-spin"></i> Guardando...</> : <><i className="fa fa-save"></i> Guardar capacitación</>}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* ── LAYOUT PRINCIPAL ── */}
                    <div className="cap-layout" style={{ display:'grid', gridTemplateColumns:'300px 1fr', gap:20 }}>

                        {/* PANEL IZQUIERDO — lista */}
                        <div className="table-card" style={{ overflow:'hidden', alignSelf:'start' }}>
                            <div style={{ padding:'14px 16px', borderBottom:'1px solid #f0f0f0', display:'flex', alignItems:'center', gap:8 }}>
                                <i className="fa fa-list" style={{ color:'#ff8a00' }}></i>
                                <span style={{ fontWeight:700, fontSize:14 }}>Capacitaciones ({items.length})</span>
                            </div>
                            <div className="cap-panel-left" style={{ maxHeight:520, overflowY:'auto' }}>
                                {loadingList ? (
                                    /* Skeleton lista: nombre(70%) + fecha(30%) */
                                    <TableSkeleton rows={5} widths={[70, 30]} />
                                ) : items.length === 0 ? (
                                    <div className="empty">
                                        <i className="fa fa-graduation-cap" style={{ fontSize:24, opacity:.3, display:'block', marginBottom:8 }}></i>
                                        No hay capacitaciones
                                    </div>
                                ) : items.map(it => {
                                    const id = it.idCapacitacion;
                                    const active = selectedCap === id;
                                    return (
                                        <div key={id} onClick={() => setSelectedCap(id)} style={{ padding:'12px 16px', cursor:'pointer', borderBottom:'1px solid #f5f5f5', background: active ? '#fff3e0':'#fff', borderLeft: active ? '3px solid #ff8a00':'3px solid transparent', transition:'background 0.15s' }}>
                                            <div style={{ fontWeight:600, fontSize:13, color:'#333', marginBottom:4 }}>{it.Nombre_curso}</div>
                                            <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                                                <span style={{ fontSize:11, color:'#aaa' }}>
                                                    {it.Fecha_Realizacion && it.Fecha_Realizacion !== 'Sin realizar' ? it.Fecha_Realizacion : 'Sin fecha'}
                                                </span>
                                                <StatusBadge fecha={it.Fecha_Vencimiento} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* PANEL DERECHO */}
                        <div style={{ display:'flex', flexDirection:'column', gap:16 }}>
                            {!selectedItem ? (
                                <div className="table-card" style={{ padding:48, textAlign:'center', color:'#bbb' }}>
                                    <i className="fa fa-graduation-cap" style={{ fontSize:40, marginBottom:12, display:'block' }}></i>
                                    Selecciona una capacitación para ver sus detalles
                                </div>
                            ) : (
                                <>
                                    {/* Detalle */}
                                    <div className="table-card" style={{ padding:20 }}>
                                        <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', marginBottom:16 }}>
                                            <div>
                                                <h3 style={{ margin:0, fontSize:17, fontWeight:800, color:'#1a1a2e' }}>{selectedItem.Nombre_curso}</h3>
                                                <div style={{ marginTop:6, display:'flex', gap:8, alignItems:'center', flexWrap:'wrap' }}>
                                                    <StatusBadge fecha={selectedItem.Fecha_Vencimiento} />
                                                    {selectedItem.Puntaje && (
                                                        <span style={{ background:'#e3f2fd', color:'#1565c0', padding:'2px 8px', borderRadius:99, fontSize:11, fontWeight:700 }}>
                                                            <i className="fa fa-star"></i> Aprobatorio: {selectedItem.Puntaje}%
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <button onClick={() => handleDelete(selectedItem.idCapacitacion)} style={{ background:'#fdecea', border:'none', color:'#c62828', padding:'8px 12px', borderRadius:8, cursor:'pointer', fontWeight:600, fontSize:12 }}>
                                                <i className="fa fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:12 }}>
                                            {[
                                                { label:'FECHA REALIZACIÓN', val: selectedItem.Fecha_Realizacion || 'Sin registrar' },
                                                { label:'FECHA VENCIMIENTO', val: selectedItem.Fecha_Vencimiento || '—' },
                                                { label:'RECURSOS',          val: recursos.length, big:true },
                                            ].map(({ label, val, big }) => (
                                                <div key={label} style={{ background:'#f9f9f9', borderRadius:8, padding:'10px 14px' }}>
                                                    <div style={{ fontSize:11, color:'#aaa', marginBottom:3 }}>{label}</div>
                                                    <div style={{ fontWeight: big ? 700:600, fontSize: big ? 20:13, color: big ? '#ff8a00':'#333' }}>{val}</div>
                                                </div>
                                            ))}
                                        </div>
                                        {selectedItem.Ruta && (
                                            <div style={{ marginTop:12 }}>
                                                <button onClick={() => handleDownloadCap(selectedItem)} className="btn-orange" style={{ fontSize:13, border:'none', cursor:'pointer' }}>
                                                    <i className="fa fa-download"></i> Descargar Certificado
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {/* Recursos */}
                                    <div className="table-card" style={{ overflow:'hidden' }}>
                                        <div style={{ padding:'12px 18px', borderBottom:'1px solid #f0f0f0', display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                                            <span style={{ fontWeight:700, fontSize:14 }}>
                                                <i className="fa fa-paperclip" style={{ color:'#ff8a00', marginRight:6 }}></i>
                                                Recursos ({recursos.length})
                                            </span>
                                            <button className="btn-orange" style={{ fontSize:12, padding:'6px 12px' }} onClick={() => setShowRecForm(v => !v)}>
                                                <i className="fa fa-plus"></i> Agregar
                                            </button>
                                        </div>

                                        {/* ── FORM AGREGAR RECURSO ── */}
                                        {showRecForm && (
                                            <div style={{ padding:'18px 20px', borderBottom:'1px solid #f0f0f0', background:'#fafbfc' }}>
                                                <form onSubmit={handleAddResource}>
                                                    <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fit,minmax(160px,1fr))', gap:12, marginBottom:14 }}>
                                                        <div className="field-block">
                                                            <label>Tipo de recurso</label>
                                                            <select className="input-text" value={recForm.tipo} onChange={e => setRecForm({ ...recForm, tipo:e.target.value })}>
                                                                <option value="archivo">📄 Archivo</option>
                                                                <option value="formulario">📋 Formulario de evaluación</option>
                                                                <option value="actualizacion">🔄 Actualización</option>
                                                            </select>
                                                        </div>
                                                        <div className="field-block">
                                                            <label>Título *</label>
                                                            <input className="input-text" value={recForm.titulo} onChange={e => setRecForm({ ...recForm, titulo:e.target.value })} placeholder="Título del recurso" />
                                                        </div>
                                                        <div className="field-block">
                                                            <label>Descripción</label>
                                                            <input className="input-text" value={recForm.descripcion} onChange={e => setRecForm({ ...recForm, descripcion:e.target.value })} placeholder="Descripción opcional" />
                                                        </div>
                                                    </div>

                                                    {/* Archivo normal */}
                                                    {recForm.tipo !== 'formulario' && (
                                                        <div className="field-block" style={{ marginBottom:14 }}>
                                                            <label>{recForm.tipo === 'archivo' ? 'Archivo *' : 'Archivo adjunto (opcional)'}</label>
                                                            <input type="file" ref={fileRecRef} className="input-text" style={{ padding:'6px 12px' }} />
                                                        </div>
                                                    )}

                                                    {/* ── BUILDER DE FORMULARIO ── */}
                                                    {recForm.tipo === 'formulario' && (
                                                        <div style={{ marginBottom:14 }}>
                                                            <div style={{ background:'#fff', border:'1px solid #e8e8e8', borderRadius:10, padding:16 }}>
                                                                <div className="field-block" style={{ marginBottom:14 }}>
                                                                    <label style={{ fontWeight:700 }}>Instrucciones generales</label>
                                                                    <textarea className="input-text" rows={2} value={instrucciones} onChange={e => setInstrucciones(e.target.value)} placeholder="Ej: Lee cada pregunta con atención antes de responder." style={{ resize:'vertical' }} />
                                                                </div>

                                                                <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:10 }}>
                                                                    <span style={{ fontWeight:700, fontSize:13 }}>Preguntas ({preguntasForm.length})</span>
                                                                    <button type="button" onClick={addPregunta} style={{ background:'#ff8a00', color:'#fff', border:'none', borderRadius:8, padding:'6px 12px', cursor:'pointer', fontSize:12, fontWeight:600 }}>
                                                                        <i className="fa fa-plus"></i> Agregar pregunta
                                                                    </button>
                                                                </div>

                                                                {preguntasForm.length === 0 && (
                                                                    <div style={{ textAlign:'center', padding:'24px 0', color:'#bbb', fontSize:13 }}>
                                                                        <i className="fa fa-clipboard" style={{ fontSize:28, display:'block', marginBottom:8, opacity:.4 }}></i>
                                                                        Agrega la primera pregunta para comenzar
                                                                    </div>
                                                                )}

                                                                {preguntasForm.map((p, idx) => (
                                                                    <div key={p.id} style={{ border:'1px solid #e8e8e8', borderRadius:10, padding:14, marginBottom:12, background:'#fafbfc' }}>
                                                                        <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:10 }}>
                                                                            <span style={{ background:'#ff8a00', color:'#fff', borderRadius:99, width:22, height:22, display:'inline-flex', alignItems:'center', justifyContent:'center', fontSize:11, fontWeight:700, flexShrink:0 }}>{idx + 1}</span>
                                                                            <input
                                                                                className="input-text"
                                                                                style={{ flex:1, marginBottom:0 }}
                                                                                value={p.texto}
                                                                                onChange={e => updatePregunta(idx, 'texto', e.target.value)}
                                                                                placeholder="Escribe la pregunta aquí..."
                                                                            />
                                                                            <select
                                                                                className="input-text"
                                                                                style={{ width:180, flexShrink:0, marginBottom:0 }}
                                                                                value={p.tipo}
                                                                                onChange={e => updatePregunta(idx, 'tipo', e.target.value)}
                                                                            >
                                                                                <option value="opcion_multiple">Opción múltiple</option>
                                                                                <option value="verdadero_falso">Verdadero / Falso</option>
                                                                                <option value="texto_libre">Texto libre</option>
                                                                            </select>
                                                                            <button type="button" onClick={() => removePregunta(idx)} title="Eliminar pregunta" style={{ background:'#fdecea', border:'none', color:'#c62828', borderRadius:6, padding:'5px 8px', cursor:'pointer', flexShrink:0 }}>
                                                                                <i className="fa fa-trash" style={{ fontSize:12 }}></i>
                                                                            </button>
                                                                        </div>

                                                                        {/* Opción múltiple */}
                                                                        {p.tipo === 'opcion_multiple' && (
                                                                            <div style={{ paddingLeft:30 }}>
                                                                                <div style={{ fontSize:11, color:'#888', marginBottom:6 }}>
                                                                                    Opciones — marca la correcta con <i className="fa fa-check-circle" style={{ color:'#2e7d32' }}></i>
                                                                                </div>
                                                                                {p.opciones.map((op, oi) => (
                                                                                    <div key={oi} style={{ display:'flex', alignItems:'center', gap:8, marginBottom:6 }}>
                                                                                        <input
                                                                                            type="radio"
                                                                                            name={`correcta_${p.id}`}
                                                                                            checked={p.correcta === oi}
                                                                                            onChange={() => updatePregunta(idx, 'correcta', oi)}
                                                                                            style={{ accentColor:'#2e7d32', width:16, height:16, flexShrink:0 }}
                                                                                            title="Marcar como respuesta correcta"
                                                                                        />
                                                                                        <input
                                                                                            className="input-text"
                                                                                            style={{ flex:1, marginBottom:0 }}
                                                                                            value={op}
                                                                                            onChange={e => updateOpcion(idx, oi, e.target.value)}
                                                                                            placeholder={`Opción ${oi + 1}`}
                                                                                        />
                                                                                        {p.opciones.length > 2 && (
                                                                                            <button type="button" onClick={() => removeOpcion(idx, oi)} style={{ background:'none', border:'none', color:'#aaa', cursor:'pointer', padding:4 }}>
                                                                                                <i className="fa fa-times"></i>
                                                                                            </button>
                                                                                        )}
                                                                                    </div>
                                                                                ))}
                                                                                <button type="button" onClick={() => addOpcion(idx)} style={{ background:'none', border:'1px dashed #ccc', borderRadius:6, padding:'4px 12px', cursor:'pointer', fontSize:12, color:'#888', marginTop:2 }}>
                                                                                    <i className="fa fa-plus"></i> Agregar opción
                                                                                </button>
                                                                            </div>
                                                                        )}

                                                                        {/* Verdadero / Falso */}
                                                                        {p.tipo === 'verdadero_falso' && (
                                                                            <div style={{ paddingLeft:30, display:'flex', gap:16 }}>
                                                                                <div style={{ fontSize:11, color:'#888', marginBottom:4, width:'100%' }}>
                                                                                    Selecciona la respuesta correcta:
                                                                                </div>
                                                                                {[['true','Verdadero'],['false','Falso']].map(([val, lbl]) => (
                                                                                    <label key={val} style={{ display:'flex', alignItems:'center', gap:8, padding:'8px 16px', borderRadius:8, border:`2px solid ${String(p.correcta) === val ? '#2e7d32':'#e0e0e0'}`, background: String(p.correcta) === val ? '#e8f5e9':'#fff', cursor:'pointer', fontWeight:600, fontSize:13, userSelect:'none' }}>
                                                                                        <input type="radio" name={`vf_${p.id}`} checked={String(p.correcta) === val} onChange={() => updatePregunta(idx, 'correcta', val)} style={{ accentColor:'#2e7d32' }} />
                                                                                        {lbl}
                                                                                    </label>
                                                                                ))}
                                                                            </div>
                                                                        )}

                                                                        {/* Texto libre */}
                                                                        {p.tipo === 'texto_libre' && (
                                                                            <div style={{ paddingLeft:30, fontSize:12, color:'#888', fontStyle:'italic' }}>
                                                                                <i className="fa fa-info-circle"></i> Las respuestas de texto libre no se califican automáticamente.
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

                                                    <div style={{ display:'flex', gap:8, justifyContent:'flex-end' }}>
                                                        <button type="button" className="btn-outline" onClick={() => setShowRecForm(false)}>Cancelar</button>
                                                        <button type="submit" className="btn-orange" disabled={loading}>
                                                            {loading ? 'Guardando...' : <><i className="fa fa-save"></i> Guardar Recurso</>}
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        )}

                                        {/* Tabla de recursos */}
                                        <div className="table-wrapper">
                                            <table className="user-table">
                                                <thead>
                                                    <tr>
                                                        <th>Título</th>
                                                        <th>Tipo</th>
                                                        <th>Descripción</th>
                                                        <th style={{ textAlign:'center' }}>Resultado</th>
                                                        <th style={{ textAlign:'center' }}>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {recursos.map(r => {
                                                        const col      = TIPO_COLORS[r.tipo] || { bg:'#f5f5f5', color:'#555', icon:'fa-file' };
                                                        const resultado = r.tipo === 'formulario' ? (resultadosMap[r.id] || null) : null;
                                                        const pAprobMin = selectedItem?.Puntaje ?? 60;
                                                        return (
                                                            <tr key={r.id}>
                                                                <td style={{ fontWeight:600 }}>{r.titulo}</td>
                                                                <td>
                                                                    <span style={{ background:col.bg, color:col.color, padding:'3px 10px', borderRadius:99, fontSize:11, fontWeight:700 }}>
                                                                        <i className={`fa ${col.icon}`} style={{ marginRight:4 }}></i>{r.tipo}
                                                                    </span>
                                                                </td>
                                                                <td style={{ color:'#888', fontSize:12 }}>{r.descripcion || '—'}</td>
                                                                <td style={{ textAlign:'center' }}>
                                                                    {resultado ? (
                                                                        <div style={{ display:'inline-flex', flexDirection:'column', alignItems:'center', gap:2 }}>
                                                                            <span style={{
                                                                                background: resultado.aprobado ? '#e8f5e9' : '#fdecea',
                                                                                color:      resultado.aprobado ? '#2e7d32' : '#c62828',
                                                                                padding:'3px 10px', borderRadius:99, fontSize:11, fontWeight:800,
                                                                            }}>
                                                                                <i className={`fa ${resultado.aprobado ? 'fa-check-circle' : 'fa-times-circle'}`} style={{ marginRight:4 }}></i>
                                                                                {resultado.mejor_puntaje}%
                                                                            </span>
                                                                            <span style={{ fontSize:10, color:'#bbb' }}>
                                                                                {resultado.intentos} intento{resultado.intentos !== 1 ? 's' : ''}
                                                                            </span>
                                                                        </div>
                                                                    ) : r.tipo === 'formulario' ? (
                                                                        <span style={{ fontSize:11, color:'#ccc' }}>Sin intentos</span>
                                                                    ) : (
                                                                        <span style={{ color:'#ddd' }}>—</span>
                                                                    )}
                                                                </td>
                                                                <td style={{ textAlign:'center' }}>
                                                                    {r.tipo === 'formulario' && r.formulario && (
                                                                        <button
                                                                            onClick={() => { setEvalRecurso(r); setShowEvalModal(true); }}
                                                                            className="action-btn"
                                                                            title={resultado?.aprobado ? 'Ver evaluación (aprobada)' : 'Tomar evaluación'}
                                                                            style={{ background: resultado?.aprobado ? '#e8f5e9' : '#e3f2fd', border:`1px solid ${resultado?.aprobado ? '#c8e6c9' : '#bbdefb'}`, borderRadius:6, cursor:'pointer', padding:'4px 8px' }}
                                                                        >
                                                                            <i className={`fa ${resultado?.aprobado ? 'fa-check' : 'fa-play-circle'}`} style={{ color: resultado?.aprobado ? '#2e7d32' : '#1565c0' }}></i>
                                                                        </button>
                                                                    )}
                                                                    {r.ruta && (
                                                                        <button onClick={() => handleDownloadRecurso(r)} className="action-btn" title={r.original_name || 'Descargar archivo'} style={{ background:'transparent', border:'none', cursor:'pointer' }}>
                                                                            <i className="fa fa-download" style={{ color:'#ff8a00' }}></i>
                                                                        </button>
                                                                    )}
                                                                    <button className="action-btn delete" onClick={() => handleDeleteResource(r.id)} title="Eliminar">
                                                                        <i className="fa fa-trash"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                    {recursos.length === 0 && (
                                                        <tr><td colSpan={5} className="empty">Sin recursos registrados</td></tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
