// javascript
// File: `resources/js/axiosSetup.js`
import axios from 'axios';

// usar la misma lógica que ya usas en otros componentes
const backend = (window.backendUrl ? `${window.backendUrl.replace(/\/$/, '')}` : '');

// Si quieres usar rutas relativas en desarrollo, deja backend vacío.
// Si tu API está en /api, no pongas '/api' aquí si tus llamadas ya usan '/api/...'
axios.defaults.baseURL = backend || '';
// Enviar cookies para usar auth por sesión (Auth::login)
axios.defaults.withCredentials = true;
// Aceptar JSON por defecto
axios.defaults.headers.common['Accept'] = 'application/json';

// Si hay token en blade/session/localStorage, añade header Bearer
if (window.currentUserToken && window.currentUserToken.length) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${window.currentUserToken}`;
}

export default axios;
