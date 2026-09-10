import './axiosSetup';
import React from 'react';
import { createRoot } from 'react-dom/client';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../css/app.css';
import '../css/Styles.css';
import Documentacion from './components/Documentacion';
import { initInactivityTimer } from './inactivityTimer.js';

initInactivityTimer();

createRoot(document.getElementById('app')).render(<Documentacion />);
