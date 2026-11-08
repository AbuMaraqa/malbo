<script setup lang="ts">
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';

import type { RoleRow } from './types';

defineProps<{ role: RoleRow }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Modal v-model:open="open" :title="`${t('Permissions')}: ${role.label}`" width="max-w-4xl">
        <div class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ role.summary.total }} {{ t('Permissions') }} / {{ role.summary.group_count }} {{ t('Groups') }}</p>

            <p v-if="role.summary.total === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ t('No permissions assigned') }}
            </p>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <section v-for="group in role.summary.groups" :key="group.label" class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ group.label }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ group.count }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span v-for="action in group.actions" :key="action" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ action }}</span>
                    </div>
                </section>
            </div>
        </div>
        <template #footer>
            <Button @click="open = false">{{ t('Close') }}</Button>
        </template>
    </Modal>
</template>
