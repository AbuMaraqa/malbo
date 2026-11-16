<script setup lang="ts">
import { computed } from 'vue';

import { brandIconFor } from './brandIcons';

/** Where an image would be: the MALBO illustration its name points to, else the name's first letter in the store's color. */
const props = defineProps<{ name: string }>();

const icon = computed(() => brandIconFor(props.name));
const letter = computed(() => Array.from(props.name)[0] ?? '');
</script>

<template>
    <span aria-hidden="true" class="grid size-full place-items-center font-bold text-store/25">
        <img v-if="icon" :src="icon" alt="" class="w-2/5 max-w-40" />
        <template v-else>{{ letter }}</template>
    </span>
</template>
