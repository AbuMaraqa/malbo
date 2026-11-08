<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

// Tiptap only downloads on the pages that use the editor.
const RichEditorField = defineAsyncComponent(() => import('./RichEditorField.vue'));

defineProps<{ label?: string; error?: string; hint?: string; required?: boolean }>();

const model = defineModel<string | null>();
</script>

<template>
    <RichEditorField v-model="model" :label="label" :error="error" :hint="hint" :required="required" />
</template>
