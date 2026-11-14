<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** The store-wide free-delivery threshold, used by zones that set none of their own. */
const props = defineProps<{ threshold: number | null }>();

const form = useForm<{ default_free_shipping_threshold: number | string | null }>({ default_free_shipping_threshold: props.threshold });

function save(): void {
    form.transform((data) => ({
        default_free_shipping_threshold: data.default_free_shipping_threshold === '' ? null : data.default_free_shipping_threshold,
    })).put(route('shipping-zones.settings'), { preserveScroll: true });
}
</script>

<template>
    <Card :title="t('Free delivery')" :description="t('A zone with its own threshold uses it instead.')">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="save">
            <TextInput
                v-model="form.default_free_shipping_threshold"
                class="w-60"
                type="number"
                min="0"
                step="0.01"
                :label="t('Free delivery from')"
                :hint="t('Leave empty for none')"
                :error="form.errors.default_free_shipping_threshold"
            />
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Save') }}</Button>
        </form>
    </Card>
</template>
