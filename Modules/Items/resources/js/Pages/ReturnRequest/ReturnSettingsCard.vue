<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** How many days after delivery a buyer may still ask from their account. Staff are not bound by it. */
const props = defineProps<{ days: number }>();

const form = useForm<{ return_window_days: number | string }>({ return_window_days: props.days });

function save(): void {
    form.put(route('return-requests.settings'), { preserveScroll: true });
}
</script>

<template>
    <Card :title="t('Return window')" :description="t('Buyers ask from their account within this many days after delivery. Staff can always add a request.')">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="save">
            <TextInput
                v-model="form.return_window_days"
                class="w-60"
                type="number"
                min="0"
                max="365"
                step="1"
                :label="t('Days to return after delivery')"
                :error="form.errors.return_window_days"
            />
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Save') }}</Button>
        </form>
    </Card>
</template>
