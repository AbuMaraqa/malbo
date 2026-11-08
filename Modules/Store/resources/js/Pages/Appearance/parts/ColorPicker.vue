<script setup lang="ts">
import { computed } from 'vue';

import ColorInput from '@/Components/form/ColorInput.vue';
import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

/**
 * The store's color: presets, or any custom color with a live contrast check. Below 4.5:1 against
 * white the storefront writes dark text on the color (BrandColor::onBrand on the server decides).
 */
defineProps<{ presets: string[]; error?: string }>();

const model = defineModel<string>({ required: true });

const isValid = computed(() => /^#[0-9a-f]{6}$/i.test(model.value));

function luminance(hex: string): number {
    const channels = [1, 3, 5].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;

        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

const ratio = computed(() => (isValid.value ? 1.05 / (luminance(model.value) + 0.05) : null));
const readsWithWhite = computed(() => (ratio.value ?? 0) >= 4.5);
</script>

<template>
    <div class="space-y-3">
        <div>
            <p class="mb-2 text-sm font-medium">{{ t('Brand color') }}</p>
            <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('Brand color')">
                <button
                    v-for="preset in presets"
                    :key="preset"
                    type="button"
                    role="radio"
                    :aria-checked="model.toUpperCase() === preset.toUpperCase()"
                    :aria-label="preset"
                    class="grid size-10 place-items-center rounded-full border-2 text-white"
                    :class="model.toUpperCase() === preset.toUpperCase() ? 'border-gray-800 dark:border-white' : 'border-transparent'"
                    :style="{ backgroundColor: preset }"
                    @click="model = preset"
                >
                    <Check v-if="model.toUpperCase() === preset.toUpperCase()" class="size-4" />
                </button>
            </div>
        </div>

        <ColorInput v-model="model" :label="t('Custom color')" :hint="t('Buttons, links and highlights use this color.')" :error="error" />

        <div v-if="ratio !== null" class="flex flex-wrap items-center gap-3 rounded-lg border border-line p-3" data-contrast-check>
            <span
                class="rounded-full px-4 py-1.5 text-sm font-bold"
                :style="{ backgroundColor: model, color: readsWithWhite ? '#FFFFFF' : '#111827' }"
            >
                {{ t('Shop now') }}
            </span>
            <p class="min-w-0 flex-1 text-sm text-muted">
                <span class="tabular" dir="ltr">{{ ratio.toFixed(1) }}:1</span>
                {{ readsWithWhite ? t('Reads well with white text.') : t('Too light for white text: your store will write on it in dark text.') }}
            </p>
        </div>
    </div>
</template>
