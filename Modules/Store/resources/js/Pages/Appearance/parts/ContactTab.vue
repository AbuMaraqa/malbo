<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import LocalizedField from './LocalizedField.vue';
import type { AppearanceForm } from './types';

defineProps<{ form: AppearanceForm }>();
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <TextInput
                v-model="form.whatsapp"
                :label="t('WhatsApp number')"
                :hint="t('International format, e.g. 970599000000. Orders are sent here.')"
                type="tel"
                :maxlength="20"
                :error="form.errors.whatsapp"
            />
            <TextInput v-model="form.phone" :label="t('Phone')" type="tel" :maxlength="20" :error="form.errors.phone" />
            <TextInput v-model="form.email" :label="t('Email')" type="email" :maxlength="120" :error="form.errors.email" />
        </div>
        <LocalizedField v-model="form.address" :form="form" name="address" :label="t('Address')" />

        <div>
            <h3 class="mb-3 text-sm font-semibold">{{ t('Social links') }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.instagram_url" label="Instagram" type="url" dir="ltr" :maxlength="255" :error="form.errors.instagram_url" />
                <TextInput v-model="form.facebook_url" label="Facebook" type="url" dir="ltr" :maxlength="255" :error="form.errors.facebook_url" />
                <TextInput v-model="form.tiktok_url" label="TikTok" type="url" dir="ltr" :maxlength="255" :error="form.errors.tiktok_url" />
                <TextInput v-model="form.x_url" label="X" type="url" dir="ltr" :maxlength="255" :error="form.errors.x_url" />
            </div>
        </div>
    </div>
</template>
