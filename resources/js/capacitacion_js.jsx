import React from 'react';
import { createRoot } from 'react-dom/client';
import '../css/Styles.css';
import axios from 'axios';
import Capacitaciones from './components/Capacitaciones.jsx';

console.log('[vite] cargando capcacitacion_js.jsx');
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const el = document.getElementById('app');
if (!el) {
    console.error('[vite] no se encontró #app en el DOM');
} else {
    try {
        createRoot(el).render(<Capacitaciones />);
        console.log('[vite] React montado en #app');
    } catch (err) {
        console.error('[vite] error montando React:', err);
        // mostrar error visible para debugging
        el.innerHTML = '<pre style="color:red;padding:20px">Error al montar React. Mira la consola para más detalles.</pre>';
    }
}
