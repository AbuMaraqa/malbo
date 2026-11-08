import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Keeps the theme in the page head in step with the server: a saved color or font shows at once,
 * without a reload. The first paint comes from the root Blade view.
 */
export function useDashboardTheme(): void {
    const page = usePage();
    const theme = computed(() => page.props.dashboardTheme as { css: string; fontsUrl: string } | undefined);

    watch(theme, (value) => {
        if (!value || typeof document === 'undefined') return;

        const style = document.getElementById('app-theme');
        const fonts = document.getElementById('app-fonts') as HTMLLinkElement | null;

        if (style && style.textContent !== value.css) style.textContent = value.css;
        if (fonts && fonts.getAttribute('href') !== value.fontsUrl) fonts.setAttribute('href', value.fontsUrl);
    });
}
