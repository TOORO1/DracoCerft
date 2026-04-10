import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.jsx',
                'resources/js/dashboard.jsx',
                'resources/js/gestor_documentacion.jsx',
                'resources/js/gestor_Usuarios.jsx',
                'resources/js/Auditoria_Reporte.jsx',
                'resources/js/permisos.jsx',
                'resources/js/capacitacion_js.jsx',
                'resources/js/configuracion.jsx',
            ],
            refresh: true,
        }),
    ],
    build: {
        // Aumentar el umbral de advertencia de chunk a 800 KB
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                /**
                 * manualChunks: extrae las librerías pesadas a chunks compartidos.
                 * Todos los 7 entry-points cargarán estos chunks desde caché del
                 * navegador en lugar de duplicarlos — reduce descarga ~60-70%.
                 */
                manualChunks: {
                    // React core — ~130 KB gz; compartido por todos los módulos
                    'vendor-react': ['react', 'react-dom'],
                    // Recharts — ~180 KB gz; solo Dashboard y Auditoría lo usan
                    'vendor-charts': ['recharts'],
                    // SweetAlert2 — ~50 KB gz; usado en múltiples páginas
                    'vendor-swal': ['sweetalert2'],
                    // Axios — ~14 KB gz
                    'vendor-axios': ['axios'],
                    // Bootstrap CSS JS — solo si se importa en JS
                    'vendor-bootstrap': ['bootstrap'],
                },
            },
        },
    },
});
