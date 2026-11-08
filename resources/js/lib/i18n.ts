import { usePage } from '@inertiajs/vue3';

/** Translate an English key from the module `Lang/*.json` files, like `__()` on the server. */
export function t(key: string, replacements: Record<string, string | number> = {}): string {
    const translations = usePage().props.translations as Record<string, string> | undefined;
    let text = translations?.[key] ?? key;

    for (const [name, value] of Object.entries(replacements)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}

/** A permission of the signed-in staff member, from the shared `auth` prop. */
export function can(permission: string): boolean {
    const auth = usePage().props.auth as { permissions?: string[] } | undefined;

    return auth?.permissions?.includes(permission) ?? false;
}
