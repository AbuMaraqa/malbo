import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

import { resolvePage } from './lib/pages';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: resolvePage,
        title: (title) => title,
        setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
    }),
);
