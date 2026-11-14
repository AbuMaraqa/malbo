<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * A campaign block's link: a page of this store opens in place, another site in a new tab. Only a
 * single "/" followed by anything but "/" or "\" is a page of this store ("/\x" is another site to
 * a browser); the server keeps nothing else but https:// and http:// addresses.
 */
const props = defineProps<{ href: string }>();

const external = computed(() => !/^\/(?![/\\])[^\\]*$/.test(props.href));
</script>

<template>
    <a v-if="external" :href="href" target="_blank" rel="noopener"><slot /></a>
    <Link v-else :href="href"><slot /></Link>
</template>
