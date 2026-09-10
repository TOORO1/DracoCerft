import React from 'react';
import { createRoot } from 'react-dom/client';
import '../css/Styles.css';
import GestorUsuarios from './components/GestorUsuarios.jsx';
import { initInactivityTimer } from './inactivityTimer.js';

initInactivityTimer();

const el = document.getElementById('app');
if (el) {
    createRoot(el).render(<GestorUsuarios />);
}
