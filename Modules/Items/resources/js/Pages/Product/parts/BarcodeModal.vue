<script setup lang="ts">
/** Choose how many labels each row prints, then download the PDF. */
import { computed, ref, watch } from 'vue';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import { Printer } from '@/lib/icons';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

interface BarcodeRow {
    id: number;
    name: string;
    code: string;
    /** Built by the server from a code holding only letters, digits and dashes. */
    svg: string;
}

const props = defineProps<{ productId: number | null; title: string }>();

const open = defineModel<boolean>('open', { required: true });

const MAX_LABELS = 1000;

const rows = ref<BarcodeRow[]>([]);
const quantities = ref<Record<number, number | string>>({});
const failed = ref(false);
const loading = ref(false);

const total = computed(() => rows.value.reduce((sum, row) => sum + (Number(quantities.value[row.id]) || 0), 0));

watch(open, async (isOpen) => {
    if (!isOpen || !props.productId) return;

    failed.value = false;
    loading.value = true;
    rows.value = [];

    try {
        const response = await fetch(route('products.barcodes', props.productId), { headers: { Accept: 'application/json' } });

        if (!response.ok) throw new Error(String(response.status));

        rows.value = ((await response.json()) as { rows: BarcodeRow[] }).rows;
        quantities.value = Object.fromEntries(rows.value.map((row) => [row.id, 1]));
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
});

function download(): void {
    if (!props.productId) return;

    const query = new URLSearchParams(rows.value.map((row) => [`quantities[${row.id}]`, String(Number(quantities.value[row.id]) || 0)]));

    window.open(`${route('products.barcodes.pdf', props.productId)}?${query.toString()}`, '_blank');
}
</script>

<template>
    <Modal v-model:open="open" :title="title" width="max-w-2xl">
        <p v-if="loading" class="text-sm text-muted">{{ t('Loading...') }}</p>
        <p v-if="failed" class="text-sm text-danger" role="alert">{{ t('Something went wrong') }}</p>
        <div class="space-y-4">
            <div v-for="row in rows" :key="row.id" class="grid items-center gap-3 border-b border-line pb-3 last:border-0 sm:grid-cols-3">
                <div class="min-w-0 sm:col-span-2">
                    <p class="truncate text-sm font-medium">{{ row.name }}</p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div class="mt-1 overflow-hidden rounded bg-white p-1" dir="ltr" v-html="row.svg" />
                    <p class="mt-1 text-xs text-muted" dir="ltr">{{ row.code }}</p>
                </div>
                <TextInput v-model="quantities[row.id]" :label="t('Quantity')" type="number" min="0" :max="MAX_LABELS" />
            </div>
        </div>
        <template #footer>
            <Button variant="primary" :disabled="total < 1 || total > MAX_LABELS" @click="download"><Printer class="size-4" />{{ t('Generate Barcode') }}</Button>
        </template>
    </Modal>
</template>
