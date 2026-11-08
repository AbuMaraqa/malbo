<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ColorInput from '@/Components/form/ColorInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

import type { FontOption, SettingsForm } from './types';

defineProps<{
    form: InertiaForm<SettingsForm>;
    colorPresets: string[];
    fonts: FontOption[];
}>();
</script>

<template>
    <Card :title="t('Dashboard appearance')" :description="t('Applies to this panel only. The storefront has its own color and font in Store appearance.')">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-medium">{{ t('Dashboard color') }}</p>
                <div class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="preset in colorPresets"
                        :key="preset"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg text-white ring-offset-2 ring-offset-white dark:ring-offset-gray-900"
                        :class="{ 'ring-2 ring-gray-400': form.dashboard_color.toLowerCase() === preset.toLowerCase() }"
                        :style="{ backgroundColor: preset }"
                        :aria-label="preset"
                        :aria-pressed="form.dashboard_color.toLowerCase() === preset.toLowerCase()"
                        @click="form.dashboard_color = preset"
                    >
                        <Check v-if="form.dashboard_color.toLowerCase() === preset.toLowerCase()" class="size-4" />
                    </button>
                </div>
                <ColorInput v-model="form.dashboard_color" :error="form.errors.dashboard_color" :hint="t('Pick a dark enough color: buttons keep white text.')" />
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">{{ t('Dashboard font') }}</legend>
                <div class="space-y-2">
                    <label
                        v-for="font in fonts"
                        :key="font.value"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3"
                        :class="form.dashboard_font === font.value ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/15' : 'border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5'"
                    >
                        <input v-model="form.dashboard_font" type="radio" name="dashboard_font" :value="font.value" class="accent-brand-500" />
                        <span class="min-w-0" :style="{ fontFamily: font.stack }">
                            <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ font.label }}</span>
                            <span class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ t('A sample of this font 1234') }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.dashboard_font" class="mt-1 text-sm text-danger" role="alert">{{ form.errors.dashboard_font }}</p>
            </fieldset>
        </div>
    </Card>
</template>
