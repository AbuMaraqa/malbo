<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { confirmState } from '@/lib/confirm';
import { t } from '@/lib/i18n';

import Button from './Button.vue';
import Modal from './Modal.vue';

const typed = ref('');

const allowed = computed(() => !confirmState.options?.typeToConfirm || typed.value === confirmState.options.typeToConfirm);

function answer(value: boolean): void {
    confirmState.open = false;
    confirmState.resolve?.(value);
    confirmState.resolve = null;
    typed.value = '';
}

watch(
    () => confirmState.open,
    (open) => {
        if (!open && confirmState.resolve) {
            answer(false);
        }
    },
);
</script>

<template>
    <Modal v-if="confirmState.options" v-model:open="confirmState.open" :title="confirmState.options.title" width="max-w-md">
        <p v-if="confirmState.options.description" class="text-sm text-muted">{{ confirmState.options.description }}</p>
        <div v-if="confirmState.options.typeToConfirm" class="mt-3">
            <label class="mb-1 block text-sm">{{ t('Type :text to confirm', { text: confirmState.options.typeToConfirm }) }}</label>
            <input v-model="typed" class="h-9 w-full rounded-control border border-line px-3 text-sm" autocomplete="off" />
        </div>
        <template #footer>
            <Button @click="answer(false)">{{ t('Cancel') }}</Button>
            <Button variant="danger" :disabled="!allowed" @click="answer(true)">{{ confirmState.options.confirmLabel ?? t('Delete') }}</Button>
        </template>
    </Modal>
</template>
