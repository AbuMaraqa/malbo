<script setup lang="ts">
import { ChevronDown } from '@/lib/icons';
import { computed, ref } from 'vue';

import { t } from '@/lib/i18n';

import type { PermissionGroup } from './types';

const props = defineProps<{ group: PermissionGroup }>();

const selected = defineModel<number[]>({ required: true });
const open = ref(false);

const ids = computed(() => props.group.permissions.map((permission) => permission.id));
const chosen = computed(() => ids.value.filter((id) => selected.value.includes(id)).length);
const allChosen = computed(() => chosen.value === ids.value.length);

function toggleAll(): void {
    const others = selected.value.filter((id) => !ids.value.includes(id));
    selected.value = allChosen.value ? others : [...others, ...ids.value];
}

function toggle(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((item) => item !== id) : [...selected.value, id];
}
</script>

<template>
    <section class="rounded-lg border border-gray-200 dark:border-gray-800">
        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-start" :aria-expanded="open" @click="open = !open">
            <span>
                <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ group.label }}</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ chosen }} / {{ ids.length }} {{ t('Permissions') }}</span>
            </span>
            <ChevronDown class="size-4 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" />
        </button>
        <div v-if="open" class="space-y-2 border-t border-gray-200 px-3 py-3 dark:border-gray-800">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                <input type="checkbox" class="size-4 rounded" :checked="allChosen" @change="toggleAll" />
                {{ t('Select All Permissions') }}
            </label>
            <div class="grid gap-2 md:grid-cols-2">
                <label v-for="permission in group.permissions" :key="permission.id" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" class="size-4 rounded" :checked="selected.includes(permission.id)" @change="toggle(permission.id)" />
                    {{ permission.label }}
                </label>
            </div>
        </div>
    </section>
</template>
