<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineProps<{ qrCodeSvg: string; secretKey: string | null }>();

const form = useForm({ code: '' });

function submit(): void {
    form.post(route('profile.two-factor.confirm'), { preserveScroll: true, onFinish: () => form.reset() });
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Scan this code with your authenticator app, then type the code it shows.') }}</p>
        <!-- SVG generated on the server by Fortify from the secret of this user, with no outside input. -->
        <div class="inline-block rounded-lg bg-white p-3" v-html="qrCodeSvg" />
        <p v-if="secretKey" class="text-sm text-gray-500 dark:text-gray-400">
            {{ t('Setup key') }}: <code class="break-all text-gray-800 dark:text-white/90" dir="ltr">{{ secretKey }}</code>
        </p>
        <form class="flex flex-wrap items-start gap-2" @submit.prevent="submit">
            <TextInput v-model="form.code" class="min-w-40" dir="ltr" required autocomplete="one-time-code" :placeholder="t('Code')" :error="form.errors.code" />
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Confirm') }}</Button>
        </form>
    </div>
</template>
