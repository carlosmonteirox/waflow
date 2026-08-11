import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

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
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
