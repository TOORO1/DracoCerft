// Language: javascript
// File: resources/js/permisos.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';
import Permisos from './components/Permisos.jsx';
import '../css/Styles.css';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

const el = document.getElementById('app');
if (el) {
    try {
        createRoot(el).render(<Permisos />);
    } catch (err) {
        console.error('Error montando Permisos React', err);
    }
} else {
    console.error('Elemento #app no encontrado');
}
