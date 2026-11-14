import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { route } from '@/lib/route';

import type { NavEntry } from './navIcons';

export interface NavLink {
    entry: NavEntry;
    /** The group the link sits in, if any. */
    parent: NavEntry | null;
}

/**
 * The sidebar entries the server sent, with their URLs and the one entry that matches the page.
 * Shared by the sidebar and the search dialog.
 */
export function useNavigation() {
    const page = usePage();

    const entries = computed(() => (page.props.sidebar ?? []) as NavEntry[]);
    const path = computed(() => page.url.split('?')[0]);

    function hrefOf(entry: NavEntry): string {
        return route(entry.route as string);
    }

    const pathOf = (entry: NavEntry): string => new URL(hrefOf(entry)).pathname;

    /** Every link, a group's children in place of the group. */
    const links = computed<NavLink[]>(() => entries.value.flatMap((entry): NavLink[] => (entry.type === 'group' ? (entry.children ?? []).map((child) => ({ entry: child, parent: entry })) : [{ entry, parent: null }])));

    /** The one link that best matches the page: on /products/add that is "Add product", not also "Products". */
    const activePath = computed(() => {
        const home = new URL(route('dashboard')).pathname;
        const matches = links.value.map(({ entry }) => pathOf(entry)).filter((link) => path.value === link || (link !== home && path.value.startsWith(`${link}/`)));

        return matches.sort((a, b) => b.length - a.length)[0] ?? null;
    });

    const isActive = (entry: NavEntry): boolean => pathOf(entry) === activePath.value;

    return { entries, links, path, hrefOf, isActive };
}
