<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import DashboardThemeCard from './DashboardThemeCard.vue';
import type { FontOption, SettingsForm } from './types';

const props = defineProps<{ form: InertiaForm<SettingsForm>; logo: ExistingImage[]; colorPresets: string[]; fonts: FontOption[] }>();

const removed = ref<(number | string)[]>([]);

watch(removed, (ids) => {
    props.form.remove_logo = ids.length > 0;
});
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <DashboardThemeCard class="lg:col-span-3 lg:order-last" :form="form" :color-presets="colorPresets" :fonts="fonts" />
        <Card class="lg:col-span-2">
            <div class="space-y-4">
                <TextInput v-model="form.site_name" :label="t('Site Name')" required :maxlength="255" :error="form.errors.site_name" />
                <TextInput v-model="form.email_address_for_contact" :label="t('Email Address For Contact')" type="email" dir="ltr" required :maxlength="255" :error="form.errors.email_address_for_contact" />
                <TextareaInput v-model="form.site_description" :label="t('Site Description')" :rows="3" required :error="form.errors.site_description" />
            </div>
        </Card>
        <Card>
            <ImageUpload v-model="form.logo" v-model:removed="removed" :label="t('Site Logo')" :existing="logo" :error="form.errors.logo" />
        </Card>
    </div>
</template>
