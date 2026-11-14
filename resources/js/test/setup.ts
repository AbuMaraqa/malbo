import { vi } from 'vitest';
import { defineComponent, h, reactive } from 'vue';

/**
 * Inertia for component tests: the page props `t()` and `route()` read (no translations, the
 * storefront's routes with the `ar` locale), a router whose calls a test can inspect, and a
 * `useForm` that records its submissions (`forms`, newest last) instead of sending them.
 */
export const page = {
    component: 'Store/Storefront/Shop',
    url: '/ar/shop',
    props: {
        translations: {} as Record<string, string>,
        ziggy: {
            url: 'http://localhost',
            port: null,
            defaults: { locale: 'ar' },
            routes: {
                'store.shop': { uri: '{locale}/shop', methods: ['GET', 'HEAD'], parameters: ['locale'] },
                'store.category': { uri: '{locale}/category/{slug}', methods: ['GET', 'HEAD'], parameters: ['locale', 'slug'] },
                'store.search.suggest': { uri: '{locale}/search/suggest', methods: ['GET', 'HEAD'], parameters: ['locale'] },
                'store.consent': { uri: '{locale}/consent', methods: ['POST'], parameters: ['locale'] },
                'store.stock-alerts.store': { uri: '{locale}/stock-alerts', methods: ['POST'], parameters: ['locale'] },
                'store.reviews.store': { uri: '{locale}/product/{slug}/reviews', methods: ['POST'], parameters: ['locale', 'slug'] },
                'store.cart.add': { uri: '{locale}/cart', methods: ['POST'], parameters: ['locale'] },
            },
        },
    },
};

export const router = {
    get: vi.fn(),
    post: vi.fn(),
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => undefined),
};

/** What a component's `useForm` holds: its fields, errors, and the calls it made. */
export type FakeForm = Record<string, unknown> & {
    errors: Record<string, string>;
    processing: boolean;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    reset: ReturnType<typeof vi.fn>;
    clearErrors: ReturnType<typeof vi.fn>;
    transformed: ((data: Record<string, unknown>) => Record<string, unknown>) | null;
    data: () => Record<string, unknown>;
};

export const forms: FakeForm[] = [];

function useForm(defaults: Record<string, unknown>): FakeForm {
    const keys = Object.keys(defaults);
    const form = reactive({
        ...defaults,
        errors: {} as Record<string, string>,
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        reset: vi.fn((...fields: string[]) => {
            for (const key of fields.length ? fields : keys) form[key] = defaults[key];
        }),
        clearErrors: vi.fn(() => (form.errors = {})),
        transformed: null,
        transform(callback: (data: Record<string, unknown>) => Record<string, unknown>) {
            form.transformed = callback;
            return form;
        },
        data: () => Object.fromEntries(keys.map((key) => [key, form[key]])),
    }) as FakeForm;

    forms.push(form);

    return form;
}

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    useForm,
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup: (props, { slots }) => () => h('a', { href: props.href }, slots.default?.()),
    }),
}));
