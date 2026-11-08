<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit(): void {
    form.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => form.reset('current_password'),
    });
}
</script>

<template>
    <Card :title="t('Change Password')">
        <form class="space-y-4" @submit.prevent="submit">
            <TextInput v-model="form.current_password" :label="t('Current Password')" type="password" dir="ltr" required autocomplete="current-password" :error="form.errors.current_password" />
            <TextInput v-model="form.password" :label="t('New Password')" type="password" dir="ltr" required autocomplete="new-password" :error="form.errors.password" />
            <TextInput v-model="form.password_confirmation" :label="t('Confirm Password')" type="password" dir="ltr" required autocomplete="new-password" />
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Change Password') }}</Button>
        </form>
    </Card>
</template>
