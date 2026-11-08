<script setup lang="ts">
import Modal from '@/Components/ui/Modal.vue';

export interface ViewField {
    label: string;
    value: string | number | null | undefined;
}

defineProps<{ title: string; fields: ViewField[] }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Modal v-model:open="open" :title="title">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div v-for="field in fields" :key="field.label" class="min-w-0 last:sm:col-span-2">
                <dt class="text-muted">{{ field.label }}</dt>
                <dd class="mt-0.5 break-words whitespace-pre-line">{{ field.value || '-' }}</dd>
            </div>
        </dl>
    </Modal>
</template>
