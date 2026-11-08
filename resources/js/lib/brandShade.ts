/** A shade of the panel color as it is on the page now, for libraries that need a real color value. */
export function brandShade(shade: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }

    return getComputedStyle(document.documentElement).getPropertyValue(`--color-brand-${shade}`).trim() || fallback;
}
