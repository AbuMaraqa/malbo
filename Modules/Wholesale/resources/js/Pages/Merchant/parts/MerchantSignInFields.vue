<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { NewMerchantForm } from './types';

/** The account a merchant staff add signs in with: its email and the password staff hand over. */
defineProps<{ form: InertiaForm<NewMerchantForm> }>();
</script>

<template>
    <Card :title="t('Sign-in details')" :description="t('The merchant signs in to the wholesale portal with this email and password. Send both to them.')">
        <div class="grid gap-4 md:grid-cols-2">
            <TextInput v-model="form.email" type="email" :label="t('Email')" dir="ltr" required autocomplete="off" :error="form.errors.email" />
            <TextInput
                v-model="form.password"
                type="text"
                :label="t('Password')"
                dir="ltr"
                required
                autocomplete="new-password"
                :hint="t('At least 8 characters.')"
                :error="form.errors.password"
            />
        </div>
    </Card>
</template>
