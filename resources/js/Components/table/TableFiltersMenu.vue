<script setup lang="ts">
import { ListFilter } from '@/lib/icons';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';

import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import type { TableFilter } from '@/types/table';

import TableFilterField from './TableFilterField.vue';

defineProps<{
    filters: TableFilter[];
    values: Record<string, string>;
    activeCount: number;
}>();

const emit = defineEmits<{ change: [key: string, value: string]; reset: [] }>();
</script>

<template>
    <PopoverRoot>
        <PopoverTrigger as-child>
            <Button>
                <ListFilter class="size-4" />
                {{ t('Filters') }}
                <span v-if="activeCount" class="tabular min-w-5 rounded-full bg-brand px-1.5 text-center text-xs leading-5 text-brand-contrast">{{ activeCount }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent align="end" :side-offset="6" :collision-padding="12" class="z-[70] w-80 max-w-[calc(100vw-1.5rem)] rounded-lg border border-line bg-card shadow-lg">
                <header class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 class="text-sm font-semibold">{{ t('Filters') }}</h3>
                    <button v-if="activeCount" type="button" class="text-sm text-danger hover:underline" @click="emit('reset')">{{ t('Reset') }}</button>
                </header>
                <div class="max-h-[60vh] space-y-4 overflow-y-auto p-4">
                    <TableFilterField v-for="filter in filters" :key="filter.key" :filter="filter" :values="values" @change="(key, value) => emit('change', key, value)" />
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
