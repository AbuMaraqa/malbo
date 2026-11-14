<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';

import { cn } from '@/lib/cn';

/** A menu that opens from a trigger: the trigger is the `trigger` slot, the items the default slot. */
withDefaults(defineProps<{ align?: 'start' | 'center' | 'end'; contentClass?: string }>(), { align: 'end', contentClass: 'w-60' });

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <DropdownMenuRoot v-model:open="open" :modal="false">
        <DropdownMenuTrigger as-child>
            <slot name="trigger" :open="open" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :align="align"
                :side-offset="10"
                :collision-padding="12"
                :class="cn('z-[70] max-h-[80vh] overflow-y-auto rounded-xl border border-line bg-card p-2 text-sm text-ink shadow-theme-lg data-[state=open]:animate-pop-in', contentClass)"
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
