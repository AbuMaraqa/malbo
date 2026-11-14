<script setup lang="ts">
import DropdownMenu from '@/Components/ui/DropdownMenu.vue';
import { t } from '@/lib/i18n';
import { MoreVertical } from '@/lib/icons';

/** A row's actions behind one ⋮ button; the items are DropdownItem. */
</script>

<template>
    <DropdownMenu content-class="w-48">
        <template #trigger="{ open }">
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                :class="{ 'bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-white': open }"
                :aria-label="t('Actions')"
            >
                <MoreVertical class="size-5" />
            </button>
        </template>
        <slot />
    </DropdownMenu>
</template>
