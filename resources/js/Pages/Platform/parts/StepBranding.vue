<script setup lang="ts">
import ColorInput from '@/Components/form/ColorInput.vue';
import Field from '@/Components/form/Field.vue';
import SelectInput from '@/Components/form/SelectInput.vue';
import { t } from '@/lib/i18n';

import type { FontOption, SignUpForm } from './signUpTypes';

defineProps<{ form: SignUpForm; fonts: FontOption[]; colors: string[] }>();

function pickLogo(form: SignUpForm, event: Event): void {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>

<template>
    <div class="space-y-4">
        <Field id="logo" :label="`${t('Logo')} (${t('optional')})`" :error="form.errors.logo" :hint="t('PNG, JPG, WEBP or GIF, up to 4 MB.')">
            <input id="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="block w-full text-sm" @change="pickLogo(form, $event)" />
        </Field>

        <ColorInput v-model="form.color" :label="t('Main color')" :error="form.errors.color" />
        <div class="flex flex-wrap gap-2">
            <button
                v-for="color in colors"
                :key="color"
                type="button"
                class="size-7 rounded-full border border-gray-200"
                :style="{ backgroundColor: color }"
                :aria-label="color"
                :aria-pressed="form.color.toLowerCase() === color.toLowerCase()"
                @click="form.color = color"
            />
        </div>

        <SelectInput v-model="form.font" :label="t('Font')" :options="fonts" :error="form.errors.font" />
    </div>
</template>
