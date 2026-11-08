import { router } from '@inertiajs/vue3';

import { confirm } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/**
 * Delete one row or the selected rows of a table, after a confirmation. `routes` is the route
 * names' prefix: `tags` in `tags.destroy`.
 */
export function useRecordDelete(routes: string) {
    async function remove(id: number, name: string): Promise<void> {
        if (await confirm({ title: t('Delete :name?', { name }), description: t('This cannot be undone.') })) {
            router.delete(route(`${routes}.destroy`, id), { preserveScroll: true });
        }
    }

    async function removeMany(ids: (number | string)[]): Promise<void> {
        if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
            router.delete(route(`${routes}.destroy-many`), { data: { ids }, preserveScroll: true });
        }
    }

    return { remove, removeMany };
}
