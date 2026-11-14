<script setup lang="ts">
/**
 * "Complete the look": the products shown next to this one on its page, in the order picked. Saved
 * on its own, apart from the product's form (it holds no files and moves no stock).
 */
import { useForm } from '@inertiajs/vue3';

import RemoteMultiSelect from '@/Components/form/RemoteMultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

const props = defineProps<{ productId: number; look: Option[] }>();

const form = useForm<{ products: (number | string)[] }>({ products: props.look.map((option) => option.value) });

function save(): void {
    form.put(route('products.look.update', props.productId), { preserveScroll: true });
}
</script>

<template>
    <Card :title="t('Complete the look')">
        <form class="space-y-3" @submit.prevent="save">
            <RemoteMultiSelect
                v-model="form.products"
                :url="route('products.look.lookup')"
                :initial="look"
                :label="t('Products shown with this one')"
                :hint="t('Up to 8, in the order you pick them. Buyers add a size to the cart from the product page.')"
                :error="form.errors.products ?? Object.entries(form.errors).find(([key]) => key.startsWith('products.'))?.[1]"
            />
            <div class="flex justify-end">
                <Button type="submit" variant="primary" :loading="form.processing">{{ t('Save') }}</Button>
            </div>
        </form>
    </Card>
</template>
