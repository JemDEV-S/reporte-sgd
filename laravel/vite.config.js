import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// En desarrollo se accede a la app por la IP de la LAN (no localhost), de modo
// que el dev server de Vite debe escuchar en todas las interfaces y anunciar esa
// misma IP; si no, el navegador intenta cargar el JS desde [::1]:5173 y lo bloquea
// por CORS (el módulo no corre y los modales caen al fallback GET sin estilos).
// La IP se toma de VITE_DEV_HOST para no fijarla a fuego; ajústala si cambia.
const devHost = process.env.VITE_DEV_HOST || '192.168.50.223';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0', // Escucha en todas las interfaces (LAN incluida).
        cors: true, // Permite la carga del módulo desde el origen de la app.
        hmr: { host: devHost }, // El cliente HMR y las URLs de los assets usan la IP de la LAN.
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
