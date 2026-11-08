<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import FormActions from '@/Components/form/FormActions.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: AdminLayout });

interface Named {
    id: number;
    name: string;
}

const props = defineProps<{
    product: Named;
    /** The product, then its variations. */
    rows: Named[];
    branches: Named[];
    /** product or variation id => branch id => price; an empty one uses the product's own price. */
    prices: Record<string, Record<string, string | number>>;
}>();

const form = useForm({ prices: props.prices });

const errors = computed(() => form.errors as Record<string, string | undefined>);

function submit(): void {
    if (form.processing) return;

    form.put(route('products.branch-pricings.update', props.product.id), { preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="`${t('Branch Pricings')}: ${product.name}`" :parents="[{ title: t('Products List'), href: route('products.index') }]" />
    <form @submit.prevent="submit">
        <Card>
            <p v-if="!branches.length" class="text-sm text-muted">{{ t('No branches yet') }}</p>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-start text-muted">
                            <th class="px-3 py-2 text-start font-medium">{{ t('Product / Variation') }}</th>
                            <th v-for="branch in branches" :key="branch.id" class="px-3 py-2 text-start font-medium">{{ branch.name }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="border-b border-line last:border-0">
                            <td class="px-3 py-2" :class="{ 'font-medium': index === 0 }">{{ row.name }}</td>
                            <td v-for="branch in branches" :key="branch.id" class="min-w-32 px-3 py-2">
                                <input
                                    v-model="form.prices[row.id][branch.id]"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputmode="decimal"
                                    :aria-label="`${row.name} - ${branch.name}`"
                                    :placeholder="t('Price')"
                                    class="h-9 w-full rounded-[10px] border bg-card px-2 text-sm text-gray-800 dark:bg-gray-900 dark:text-white/90"
                                    :class="errors[`prices.${row.id}.${branch.id}`] ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'"
                                />
                                <p v-if="errors[`prices.${row.id}.${branch.id}`]" class="mt-1 text-xs text-danger" role="alert">{{ errors[`prices.${row.id}.${branch.id}`] }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
        <FormActions :processing="form.processing" :cancel-href="route('products.index')" />
    </form>
</template>
