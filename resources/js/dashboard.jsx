import React from 'react';
import { createRoot } from 'react-dom/client';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../css/app.css';
import '../css/Styles.css';
import Dashboard from './components/Dashboard.jsx';

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(<Dashboard />);
}
