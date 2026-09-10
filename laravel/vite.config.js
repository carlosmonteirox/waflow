import { defaultAllowedOrigins, defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const hmrClientPort = Number(process.env.VITE_HMR_CLIENT_PORT || 5173);
const appUrl = process.env.APP_URL;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/styles.css', 'resources/js/app.js'],
            refresh: [
                'app/Http/Controllers/**/*.php',
                'resources/views/**/*.blade.php',
                'resources/js/**/*.vue',
                'resources/js/**/*.js',
                'resources/css/**/*.css',
                'public/assets/css/styles.css',
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        origin: process.env.VITE_DEV_SERVER_URL,
        cors: {
            origin: [
                defaultAllowedOrigins,
                ...(appUrl ? [appUrl] : []),
            ],
        },
        hmr: process.env.VITE_HMR_HOST
            ? {
                host: process.env.VITE_HMR_HOST,
                clientPort: hmrClientPort,
            }
            : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
