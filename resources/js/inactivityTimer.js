/**
 * inactivityTimer.js
 * Detecta inactividad del usuario y cierra sesión con un aviso previo.
 * Usa window.sessionTimeoutMinutos (definido en cada blade autenticado)
 * o cae de vuelta a 60 minutos.
 */
import Swal from 'sweetalert2';
import axios from 'axios';

export function initInactivityTimer() {
    const TOTAL_MS    = (parseInt(window.sessionTimeoutMinutos) || 60) * 60 * 1000;
    const WARN_MS     = 2 * 60 * 1000;   // aviso 2 min antes
    const WARN_SECS   = 120;             // segundos del contador

    let mainTimer = null;
    let warnTimer = null;
    let countdownInterval = null;
    let warned = false;

    function resetTimer() {
        if (warned) return;
        clearTimeout(mainTimer);
        clearTimeout(warnTimer);

        // Si el timeout es menor a 2 min, no hay aviso, logout directo
        if (TOTAL_MS <= WARN_MS) {
            mainTimer = setTimeout(doLogout, TOTAL_MS);
        } else {
            warnTimer = setTimeout(showWarning, TOTAL_MS - WARN_MS);
            mainTimer = setTimeout(doLogout, TOTAL_MS);
        }
    }

    function showWarning() {
        warned = true;
        let remaining = WARN_SECS;

        Swal.fire({
            title: 'Sesión a punto de expirar',
            html: `
                <p style="color:#555;font-size:14px;margin:0 0 12px">
                    Por inactividad, tu sesión se cerrará en
                </p>
                <div id="dracocert-countdown" style="
                    font-size:36px;font-weight:900;color:#ff8a00;
                    letter-spacing:2px;margin:4px 0 16px;
                ">2:00</div>
                <p style="color:#888;font-size:12px;margin:0">
                    Haz clic en <strong>Continuar</strong> para seguir usando el sistema.
                </p>
            `,
            icon: 'warning',
            confirmButtonText: '<i class="fa fa-play"></i> Continuar sesión',
            confirmButtonColor: '#ff8a00',
            showCancelButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                const el = document.getElementById('dracocert-countdown');
                countdownInterval = setInterval(() => {
                    remaining--;
                    if (el) {
                        const m = Math.floor(remaining / 60);
                        const s = remaining % 60;
                        el.textContent = `${m}:${String(s).padStart(2, '0')}`;
                    }
                    if (remaining <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            },
            willClose: () => {
                clearInterval(countdownInterval);
            },
        }).then(result => {
            if (result.isConfirmed) {
                warned = false;
                resetTimer();
            }
            // Si no confirma (p.e. se cerró por el logout automático), no importa
        });
    }

    function doLogout() {
        clearInterval(countdownInterval);
        Swal.close();

        // Mostrar aviso brevemente y luego redirigir automáticamente
        Swal.fire({
            title: 'Sesión cerrada',
            text: 'Tu sesión fue cerrada por inactividad.',
            icon: 'info',
            confirmButtonColor: '#ff8a00',
            confirmButtonText: 'Iniciar sesión',
            allowOutsideClick: false,
            timer: 4000,               // auto-cierra a los 4s si no hace clic
            timerProgressBar: true,
        }).then(() => {
            submitLogout();            // única llamada, con guard logoutSubmitted
        });
    }

    let logoutSubmitted = false;
    function submitLogout() {
        if (logoutSubmitted) return;
        logoutSubmitted = true;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Intentar logout correcto vía axios (registra en activity_log).
        // Si la sesión ya expiró (419) o cualquier otro error, igualmente
        // redirigimos a /login — el usuario ya está desautenticado.
        axios.post('/logout', {}, {
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            }
        })
        .catch(() => { /* sesión expirada u otro error — ignorar */ })
        .finally(() => {
            window.location.href = '/login';
        });
    }

    // Eventos que reinician el temporizador
    const EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
    EVENTS.forEach(ev => document.addEventListener(ev, resetTimer, { passive: true }));

    // Arrancar
    resetTimer();
}
