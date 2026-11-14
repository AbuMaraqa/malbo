import vue from '@vitejs/plugin-vue';
import Icons from 'unplugin-icons/vite';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Component tests (`npm run test:js`): the app's aliases and icons, in jsdom. Inertia's page props
 * and router are stubbed in resources/js/test/setup.ts.
 */
export default defineConfig({
    plugins: [vue(), Icons({ compiler: 'vue3' })],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@modules': fileURLToPath(new URL('./Modules', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/__tests__/**/*.spec.ts', 'Modules/*/resources/js/**/__tests__/**/*.spec.ts'],
        setupFiles: ['resources/js/test/setup.ts'],
    },
});
