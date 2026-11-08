<script setup lang="ts">
import { ChevronDown, X } from '@/lib/icons';
import { ref } from 'vue';

import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';

import type { AppVersionForm } from './types';

defineProps<{
    /** Errors of this item, by field name. */
    errors: Record<string, string | undefined>;
    /** Saved platforms stay, as in `core`; a row added just now can still be removed. */
    removable: boolean;
}>();
defineEmits<{ remove: [] }>();

const version = defineModel<AppVersionForm>({ required: true });
const open = ref(!version.value.platform);
</script>

<template>
    <section class="rounded-lg border border-gray-200 dark:border-gray-800">
        <header class="flex items-center justify-between gap-3 px-4 py-3">
            <button type="button" class="flex flex-1 items-center gap-2 text-start text-sm font-medium text-gray-800 dark:text-white/90" :aria-expanded="open" @click="open = !open">
                <ChevronDown class="size-4 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" />
                {{ version.platform || t('New platform') }}
            </button>
            <Button v-if="removable" variant="ghost" size="icon" :aria-label="t('Remove')" @click="$emit('remove')"><X class="size-4" /></Button>
        </header>
        <!-- v-show: a closed item keeps its fields, and opens when one of them has an error. -->
        <div v-show="open || Object.keys(errors).length > 0" class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-800">
            <TextInput v-model="version.platform" :label="t('Platform (e.g., android, ios)')" dir="ltr" required :maxlength="255" :error="errors.platform" />
            <TextInput v-model="version.store_url" :label="t('Store URL')" type="url" dir="ltr" required :maxlength="255" :error="errors.store_url" />
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="version.min_version" :label="t('Min Version')" dir="ltr" required :maxlength="255" :error="errors.min_version" />
                <TextInput v-model="version.latest_version" :label="t('Latest Version')" dir="ltr" required :maxlength="255" :error="errors.latest_version" />
            </div>
            <Toggle v-model="version.maintenance_mode" :label="t('Maintenance Mode')" />
            <TextareaInput v-model="version.message" :label="t('Maintenance / Update Message')" :error="errors.message" />
        </div>
    </section>
</template>
