import './axiosSetup';
import '../css/Styles.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import Configuracion from './components/Configuracion';
import { initInactivityTimer } from './inactivityTimer.js';

initInactivityTimer();

const root = document.getElementById('app');
if (root) createRoot(root).render(<Configuracion />);
