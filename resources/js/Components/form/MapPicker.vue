<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

// Leaflet only downloads on the pages with a map.
const MapPickerField = defineAsyncComponent(() => import('./MapPickerField.vue'));

defineProps<{ label?: string; error?: string; hint?: string; center?: [number, number] }>();

const latitude = defineModel<number | string | null>('latitude');
const longitude = defineModel<number | string | null>('longitude');
</script>

<template>
    <MapPickerField v-model:latitude="latitude" v-model:longitude="longitude" :label="label" :error="error" :hint="hint" :center="center" />
</template>
