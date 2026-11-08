<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';

/** One sensitive two-factor step: asks for the password, then calls the route. */
const props = defineProps<{ url: string; method?: 'post' | 'delete'; label: string; danger?: boolean }>();

const form = useForm({ password: '' });

function submit(): void {
    form.submit(props.method ?? 'post', props.url, { preserveScroll: true, onFinish: () => form.reset() });
}
</script>

<template>
    <form class="flex flex-wrap items-start gap-2" @submit.prevent="submit">
        <TextInput v-model="form.password" class="min-w-56 flex-1" type="password" dir="ltr" required autocomplete="current-password" :placeholder="t('Current Password')" :error="form.errors.password" />
        <Button type="submit" :variant="danger ? 'danger' : 'primary'" :loading="form.processing">{{ label }}</Button>
    </form>
</template>
