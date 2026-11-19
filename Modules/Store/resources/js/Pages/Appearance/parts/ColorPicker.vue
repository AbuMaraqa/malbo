<script setup lang="ts">
import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

/**
 * The store's color, from the MALBO brand guidelines only (config/store.php `color_presets`): the
 * surfaces its white logo may sit on. Each reads well with white text.
 */
defineProps<{ presets: string[]; error?: string }>();

const model = defineModel<string>({ required: true });

const chosen = (preset: string): boolean => model.value.toUpperCase() === preset.toUpperCase();
</script>

<template>
    <div class="space-y-2">
        <p class="text-sm font-medium">{{ t('Brand color') }}</p>
        <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('Brand color')">
            <button
                v-for="preset in presets"
                :key="preset"
                type="button"
                role="radio"
                :aria-checked="chosen(preset)"
                :aria-label="preset"
                class="grid size-10 place-items-center rounded-full border-2 text-white"
                :class="chosen(preset) ? 'border-gray-800 dark:border-white' : 'border-transparent'"
                :style="{ backgroundColor: preset }"
                @click="model = preset"
            >
                <Check v-if="chosen(preset)" class="size-4" />
            </button>
        </div>
        <p class="text-sm text-muted">{{ t('The MALBO brand colors only, as its guidelines ask. Buttons, links and highlights use this color.') }}</p>
        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
    </div>
</template>
