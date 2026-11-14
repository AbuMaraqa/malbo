<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { btnBrand, btnOutline, card, field, fieldLabel } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import type { PortalLink, StatementData } from './parts/types';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    nav: PortalLink[];
    filters: { from: string; to: string };
    statement: StatementData;
    pdfUrl: string;
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function show(): void {
    router.get(route('store.wholesale.statement'), { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <PortalShell :title="t('Statement of account')" :nav="nav" current="statement">
        <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="show">
            <div>
                <label for="statement-from" :class="fieldLabel">{{ t('From') }}</label>
                <input id="statement-from" v-model="from" type="date" :class="field" />
            </div>
            <div>
                <label for="statement-to" :class="fieldLabel">{{ t('To') }}</label>
                <input id="statement-to" v-model="to" type="date" :class="field" />
            </div>
            <button type="submit" :class="btnBrand">{{ t('Show') }}</button>
            <a :href="pdfUrl" target="_blank" rel="noopener" :class="btnOutline">{{ t('Statement as PDF') }}</a>
        </form>

        <dl v-if="statement.credit" class="mb-6 grid gap-3 sm:grid-cols-4">
            <div :class="card" class="p-4">
                <dt class="text-sm text-(--ink-soft)">{{ t('Credit limit') }}</dt>
                <dd class="mt-1 font-bold">{{ statement.credit.limit_label }}</dd>
            </div>
            <div :class="card" class="p-4">
                <dt class="text-sm text-(--ink-soft)">{{ t('Balance due') }}</dt>
                <dd class="mt-1 font-bold">{{ statement.credit.balance_label }}</dd>
            </div>
            <div :class="card" class="p-4">
                <dt class="text-sm text-(--ink-soft)">{{ t('Open orders') }}</dt>
                <dd class="mt-1 font-bold">{{ statement.credit.open_label }}</dd>
            </div>
            <div :class="card" class="p-4">
                <dt class="text-sm text-(--ink-soft)">{{ t('Credit left') }}</dt>
                <dd class="mt-1 font-bold">{{ statement.credit.room_label }}</dd>
            </div>
        </dl>

        <section :class="card" class="overflow-x-auto" :aria-label="t('Statement of account')">
            <table class="w-full min-w-max text-sm">
                <thead>
                    <tr class="border-b border-line bg-(--sand-light)">
                        <th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('Date') }}</th>
                        <th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('Description') }}</th>
                        <th scope="col" class="px-3 py-2 text-end font-semibold">{{ t('Debit') }}</th>
                        <th scope="col" class="px-3 py-2 text-end font-semibold">{{ t('Credit') }}</th>
                        <th scope="col" class="px-3 py-2 text-end font-semibold">{{ t('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr class="bg-(--sand-light)/50 font-semibold">
                        <td class="px-3 py-2" colspan="4">{{ t('Balance brought forward') }}</td>
                        <td class="px-3 py-2 text-end">{{ statement.opening_label }}</td>
                    </tr>
                    <tr v-for="row in statement.rows" :key="row.id">
                        <td class="px-3 py-2" dir="ltr">{{ row.date }}</td>
                        <td class="px-3 py-2">
                            <span class="font-semibold">{{ row.type_label }}</span> · {{ row.description }}
                            <p v-if="row.note" class="text-xs text-(--ink-soft)">{{ row.note }}</p>
                        </td>
                        <td class="px-3 py-2 text-end">{{ row.debit_label ?? '' }}</td>
                        <td class="px-3 py-2 text-end">{{ row.credit_label ?? '' }}</td>
                        <td class="px-3 py-2 text-end font-semibold">{{ row.balance_label }}</td>
                    </tr>
                    <tr v-if="!statement.rows.length">
                        <td class="px-3 py-6 text-center text-(--ink-soft)" colspan="5">{{ t('No entries in these dates.') }}</td>
                    </tr>
                    <tr class="bg-(--sand-light)/50 font-bold">
                        <td class="px-3 py-2" colspan="2">{{ t('Totals and closing balance') }}</td>
                        <td class="px-3 py-2 text-end">{{ statement.debits_label }}</td>
                        <td class="px-3 py-2 text-end">{{ statement.credits_label }}</td>
                        <td class="px-3 py-2 text-end">{{ statement.closing_label }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        <p class="mt-3 text-sm text-(--ink-soft)">{{ t('A positive balance is what the shop owes.') }}</p>
    </PortalShell>
</template>
