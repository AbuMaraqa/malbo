<script setup lang="ts">
import { Columns3 } from '@/lib/icons';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';

import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import type { TableColumn } from '@/types/table';

defineProps<{ columns: TableColumn[]; hidden: string[] }>();

const emit = defineEmits<{ toggle: [key: string] }>();
</script>

<template>
    <PopoverRoot>
        <PopoverTrigger as-child>
            <Button :aria-label="t('Columns')"><Columns3 class="size-4" /></Button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent align="end" :side-offset="6" :collision-padding="12" class="z-[70] w-56 rounded-lg border border-line bg-card p-2 shadow-lg">
                <p class="px-2 py-1 text-sm font-semibold">{{ t('Columns') }}</p>
                <label v-for="column in columns" :key="column.key" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-surface">
                    <input type="checkbox" class="accent-brand" :checked="!hidden.includes(column.key)" @change="emit('toggle', column.key)" />
                    {{ column.label }}
                </label>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
