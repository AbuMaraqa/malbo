import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface Locale {
    code: string;
    native: string;
    flag: string;
    dir: 'rtl' | 'ltr';
}

/** The site's languages, the current one, and the same page in another language. */
export function useLocales() {
    const page = usePage();

    const locales = computed(() => (page.props.locales ?? []) as Locale[]);
    const current = computed(() => locales.value.find((locale) => locale.code === page.props.locale));

    /** A full load: the texts and the direction change with it. */
    function urlFor(code: string): string {
        const codes = locales.value.map((locale) => locale.code).join('|');

        return page.url.replace(new RegExp(`^/(${codes})(?=/|$|\\?)`), `/${code}`);
    }

    return { locales, current, urlFor };
}
