<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The arrow that points forward in the page's reading direction. */
const page = usePage();
const arrow = computed(() => (page.props.dir === 'rtl' ? '←' : '→'));
</script>

<template>
    <span aria-hidden="true">{{ arrow }}</span>
</template>
