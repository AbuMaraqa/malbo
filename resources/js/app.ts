import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, createSSRApp, h } from 'vue';

import { resolvePage } from './lib/pages';

createInertiaApp({
    resolve: resolvePage,
    title: (title) => title,
    progress: { color: 'var(--color-brand-500)', delay: 150 },
    setup({ el, App, props, plugin }) {
        const root = el?.hasChildNodes() ? createSSRApp : createApp;

        root({ render: () => h(App, props) }).use(plugin).mount(el as Element);
    },
});
