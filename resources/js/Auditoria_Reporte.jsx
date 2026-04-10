// File: resources/js/Auditoria_Reporte.jsx
// Language: javascript
import React from 'react';
import { createRoot } from 'react-dom/client';
import '../css/Styles.css';
import axios from 'axios';
import Auditoria from './components/Auditoria.jsx';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

const el = document.getElementById('app');
if (!el) {
    console.error('[vite] no se encontró #app en el DOM');
} else {
    try {
        createRoot(el).render(<Auditoria />);
        console.log('[vite] React montado en #app');
    } catch (err) {
        console.error('[vite] error montando React:', err);
        el.innerHTML = '<pre style="color:red;padding:20px">Error al montar React. Mira la consola para más detalles.</pre>';
    }
}
