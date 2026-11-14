import { usePage } from '@inertiajs/vue3';

/** Translate an English key from the module `Lang/*.json` files, like `__()` on the server. */
export function t(key: string, replacements: Record<string, string | number> = {}): string {
    const translations = usePage().props.translations as Record<string, string> | undefined;
    const text = translations?.[key] ?? key;

    if (Object.keys(replacements).length === 0) return text;

    // One pass, longest name first: `:to` never eats the start of `:total`.
    const names = Object.keys(replacements).sort((a, b) => b.length - a.length);
    const pattern = new RegExp(`:(${names.map((name) => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'g');

    return text.replace(pattern, (_, name: string) => String(replacements[name]));
}

/** A permission of the signed-in staff member, from the shared `auth` prop. */
export function can(permission: string): boolean {
    const auth = usePage().props.auth as { permissions?: string[] } | undefined;

    return auth?.permissions?.includes(permission) ?? false;
}
