import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import Icons from 'unplugin-icons/vite';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: ['Modules/**/routes/**', 'Modules/**/Lang/**', 'routes/**', 'resources/views/**'],
        }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
        tailwindcss(),
        Icons({ compiler: 'vue3' }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@modules': fileURLToPath(new URL('./Modules', import.meta.url)),
        },
    },
    // No `host` here: laravel-vite-plugin serves on the APP_URL host with its Herd/Valet
    // certificate (https://malbo.test:5173). Stores are subdomains of it or of localhost.
    server: {
        cors: { origin: /^https?:\/\/([a-z0-9-]+\.)*(localhost|[a-z0-9-]+\.test)(:\d+)?$/ },
        watch: { ignored: ['**/storage/**', '**/vendor/**'] },
    },
});
