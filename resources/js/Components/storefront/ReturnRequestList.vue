<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { AccountReturn } from './types';

/** The order's return and exchange requests, newest first, with where each stands. */
const props = defineProps<{ requests: AccountReturn[]; orderNumber: string }>();

const cancelling = ref<number | null>(null);

function cancel(request: AccountReturn): void {
    cancelling.value = request.id;
    router.post(
        route('store.account.orders.returns.cancel', { number: props.orderNumber, returnRequest: request.id }),
        {},
        { preserveScroll: true, onFinish: () => (cancelling.value = null) },
    );
}

const tones: Record<string, string> = {
    requested: 'bg-(--sand-light)',
    approved: 'bg-store-50 text-store-800',
    received: 'bg-store-50 text-store-800',
    completed: 'bg-store-50 text-store-800',
    rejected: 'bg-(--shell) text-(--danger) border border-(--danger)',
};
</script>

<template>
    <ul class="grid gap-4">
        <li v-for="request in requests" :key="request.id" class="rounded-md border border-line p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold">
                    {{ request.type }} · <span class="font-normal text-(--ink-soft)">{{ request.reason }}</span>
                </p>
                <span class="rounded-full px-3 py-1 text-sm font-bold" :class="tones[request.status.key] ?? 'bg-(--sand-light)'">{{ request.status.label }}</span>
            </div>
            <ul class="mt-3 grid gap-1 text-sm">
                <li v-for="(line, index) in request.lines" :key="index">
                    {{ line.quantity }} × {{ line.name }}
                    <span v-if="line.options.length" class="text-(--ink-soft)">({{ line.options.map((option) => option.value).join(' · ') }})</span>
                    <span v-if="line.exchange_for" class="text-(--ink-soft)"> → {{ line.exchange_for }}</span>
                </li>
            </ul>
            <p v-if="request.customer_note" class="mt-2 text-sm text-(--ink-soft)">{{ request.customer_note }}</p>
            <p v-if="request.refund_label" class="mt-2 text-sm">{{ t('Refund') }}: <span class="font-bold">{{ request.refund_label }}</span></p>
            <ol class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                <li v-for="(row, index) in request.history" :key="index">
                    {{ row.status }} <span dir="ltr">{{ row.date }}</span>
                </li>
            </ol>
            <button
                v-if="request.can_cancel"
                type="button"
                class="mt-3 cursor-pointer text-sm font-bold text-(--danger) underline-offset-4 hover:underline disabled:opacity-50"
                :disabled="cancelling !== null"
                @click="cancel(request)"
            >
                {{ t('Cancel the request') }}
            </button>
        </li>
    </ul>
</template>
