<script setup lang="ts">
import { computed } from 'vue';

import { Badge } from '@/tailadmin/ui/badge';

const props = withDefaults(defineProps<{ tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand' }>(), { tone: 'neutral' });

const color = computed(() => ({ neutral: 'gray', success: 'success', warning: 'warning', danger: 'error', info: 'info', brand: 'primary' } as const)[props.tone]);
</script>

<template>
    <Badge variant="light" :color="color" size="sm"><slot /></Badge>
</template>
