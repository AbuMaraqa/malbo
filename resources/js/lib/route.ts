import { usePage } from '@inertiajs/vue3';
import { route as ziggyRoute, type Config } from 'ziggy-js';

/** URL of a named Laravel route, on the current host, with `{locale}` filled by the server. */
export function route(name: string, params?: unknown): string {
    return ziggyRoute(name as never, params as never, undefined, usePage().props.ziggy as unknown as Config);
}
