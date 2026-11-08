<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';

const props = withDefaults(defineProps<{ label?: string; error?: string; hint?: string; center?: [number, number] }>(), {
    center: () => [31.9, 35.2],
});

const latitude = defineModel<number | string | null>('latitude');
const longitude = defineModel<number | string | null>('longitude');

const element = ref<HTMLElement>();
let map: L.Map | null = null;
let marker: L.CircleMarker | null = null;

function position(): [number, number] | null {
    const lat = Number(latitude.value);
    const lng = Number(longitude.value);

    return latitude.value !== null && latitude.value !== '' && longitude.value !== null && longitude.value !== '' && Number.isFinite(lat) && Number.isFinite(lng)
        ? [lat, lng]
        : null;
}

function place(at: [number, number]): void {
    if (!map) return;

    if (marker) {
        marker.setLatLng(at);
    } else {
        const brand = getComputedStyle(document.documentElement).getPropertyValue('--brand').trim();
        marker = L.circleMarker(at, { radius: 9, color: '#fff', weight: 2, fillColor: brand, fillOpacity: 1 }).addTo(map);
    }
}

onMounted(() => {
    const start = position();

    map = L.map(element.value!).setView(start ?? props.center, start ? 15 : 8);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (start) place(start);

    map.on('click', (event: L.LeafletMouseEvent) => {
        latitude.value = Number(event.latlng.lat.toFixed(7));
        longitude.value = Number(event.latlng.lng.toFixed(7));
    });
});

watch([latitude, longitude], () => {
    const at = position();
    if (at) place(at);
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <Field :label="label" :error="error" :hint="hint ?? t('Click the map to set the location')">
        <div ref="element" class="relative z-0 h-72 w-full rounded-control border border-line" />
    </Field>
</template>
