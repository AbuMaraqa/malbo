<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { card } from '@/Components/storefront/styles';
import { t } from '@/lib/i18n';
import { Trash2 } from '@/lib/icons';

import type { CartGroup } from './types';

/** One product in the wholesale cart: its sizes, live unit prices, the break reached and what blocks it. */
defineProps<{ group: CartGroup; busy: boolean; editable: boolean }>();

const emit = defineEmits<{ remove: [] }>();
</script>

<template>
    <article :class="card" class="p-5">
        <header class="flex items-start gap-4">
            <div class="size-20 shrink-0 overflow-hidden rounded-md bg-(--sand)">
                <img v-if="group.image" :src="group.image" :alt="group.name" class="size-full object-cover" loading="lazy" />
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="font-bold">
                    <Link v-if="group.url" :href="group.url" class="hover:underline">{{ group.name }}</Link>
                    <template v-else>{{ group.name }}</template>
                </h3>
                <p class="text-sm text-muted">
                    {{ t(':count pieces', { count: group.quantity }) }}
                    <span v-if="group.break_label" class="text-store-700">· {{ group.break_label }}</span>
                    <span v-if="!group.by_piece">· {{ t('In size runs') }}</span>
                </p>
                <p v-if="group.problem" class="mt-1 text-sm font-bold text-(--danger)">{{ group.problem }}</p>
            </div>
            <div v-if="editable" class="flex shrink-0 items-center gap-2">
                <Link v-if="group.url" :href="group.url" class="text-sm font-semibold text-store-700 underline underline-offset-4">{{ t('Change quantities') }}</Link>
                <button
                    type="button"
                    class="grid size-10 cursor-pointer place-items-center rounded-md text-muted hover:bg-(--sand-light) hover:text-ink disabled:opacity-40"
                    :aria-label="t('Remove :name', { name: group.name })"
                    :disabled="busy"
                    @click="emit('remove')"
                >
                    <Trash2 class="size-5" aria-hidden="true" />
                </button>
            </div>
        </header>

        <table class="mt-4 w-full text-sm">
            <thead class="sr-only">
                <tr>
                    <th>{{ t('Item') }}</th>
                    <th>{{ t('Quantity') }}</th>
                    <th>{{ t('Unit price') }}</th>
                    <th>{{ t('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <tr v-for="line in group.lines" :key="line.id" :class="{ 'opacity-70': line.problem }">
                    <td class="py-2">
                        <span v-if="line.options.length">{{ line.options.map((option) => option.value).join(' / ') }}</span>
                        <span v-else>{{ group.name }}</span>
                        <p v-if="line.problem" class="text-xs font-bold text-(--danger)">{{ line.problem }}</p>
                        <p v-else-if="line.preorder > 0" class="text-xs text-(--ink-soft)">{{ t('Pre-order: :count pieces', { count: line.preorder }) }}<template v-if="line.incoming_label"> · {{ line.incoming_label }}</template></p>
                    </td>
                    <td class="py-2 text-center tabular-nums">× {{ line.quantity }}</td>
                    <td class="py-2 text-center">{{ line.unit_label ?? '—' }}</td>
                    <td class="py-2 text-end font-semibold">{{ line.total_label }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2 text-end font-bold">{{ group.total_label }}</p>
    </article>
</template>
