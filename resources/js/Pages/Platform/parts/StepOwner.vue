<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { SignUpForm } from './signUpTypes';

defineProps<{ form: SignUpForm }>();
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <TextInput v-model="form.ownerName" :label="t('Full name')" required :maxlength="120" autocomplete="name" :error="form.errors.ownerName" />
            <TextInput v-model="form.ownerPhone" :label="`${t('Mobile number')} (${t('optional')})`" type="tel" :maxlength="30" autocomplete="tel" dir="ltr" :error="form.errors.ownerPhone" />
        </div>
        <TextInput v-model="form.ownerEmail" :label="t('Email')" type="email" required autocomplete="email" dir="ltr" :error="form.errors.ownerEmail" />
        <div class="grid gap-4 sm:grid-cols-2">
            <TextInput v-model="form.password" :label="t('Password')" type="password" required autocomplete="new-password" dir="ltr" :error="form.errors.password" />
            <TextInput v-model="form.password_confirmation" :label="t('Confirm password')" type="password" required autocomplete="new-password" dir="ltr" />
        </div>
    </div>
</template>
