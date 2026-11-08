import type { DefineComponent } from 'vue';

const pages = import.meta.glob<{ default: DefineComponent }>([
    '../Pages/**/*.vue',
    '../../../Modules/*/resources/js/Pages/**/*.vue',
]);

const MODULES = ['Core', 'Items', 'GeoLocation', 'Branch', 'Store'];

/** `Core/Currency/Index` lives in its module; `Auth/Login` in resources/js/Pages. */
export async function resolvePage(name: string): Promise<DefineComponent> {
    const [first, ...rest] = name.split('/');
    const path = MODULES.includes(first)
        ? `../../../Modules/${first}/resources/js/Pages/${rest.join('/')}.vue`
        : `../Pages/${name}.vue`;
    const page = pages[path];

    if (!page) {
        throw new Error(`Page not found: ${name}`);
    }

    return (await page()).default;
}
