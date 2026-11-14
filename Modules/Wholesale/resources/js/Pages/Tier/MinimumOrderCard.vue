<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** The store's wholesale minimum order, by value and by pieces. A merchant may have its own. */
const props = defineProps<{ settings: { minimum_order_value: number | null; minimum_order_pieces: number | null } }>();

const form = useForm<{ minimum_order_value: number | string | null; minimum_order_pieces: number | string | null }>({
    minimum_order_value: props.settings.minimum_order_value,
    minimum_order_pieces: props.settings.minimum_order_pieces,
});

function save(): void {
    form.transform((data) => ({
        minimum_order_value: data.minimum_order_value === '' ? null : data.minimum_order_value,
        minimum_order_pieces: data.minimum_order_pieces === '' ? null : data.minimum_order_pieces,
    })).put(route('wholesale-tiers.settings'), { preserveScroll: true });
}
</script>

<template>
    <Card :title="t('Minimum wholesale order')" :description="t('Checkout is refused below it. A merchant with its own minimum uses that instead.')">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="save">
            <TextInput
                v-model="form.minimum_order_value"
                class="w-60"
                type="number"
                min="0"
                step="0.01"
                :label="t('Minimum order value')"
                :hint="t('Leave empty for none')"
                :error="form.errors.minimum_order_value"
            />
            <TextInput
                v-model="form.minimum_order_pieces"
                class="w-60"
                type="number"
                min="0"
                :label="t('Minimum order pieces')"
                :hint="t('Leave empty for none')"
                :error="form.errors.minimum_order_pieces"
            />
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Save') }}</Button>
        </form>
    </Card>
</template>
