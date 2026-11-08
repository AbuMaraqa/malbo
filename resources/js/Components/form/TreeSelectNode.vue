<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@/lib/icons';
import { computed } from 'vue';

import { t } from '@/lib/i18n';

export interface TreeNode {
    id: number | string;
    label: string;
    children?: TreeNode[];
}

const props = defineProps<{
    node: TreeNode;
    depth: number;
    multiple?: boolean;
    /** The radio group of a single choice. */
    group: string;
    selected: Set<string>;
    disabled: Set<string>;
    expanded: Set<string>;
    /** A search is on: every branch that is shown is open. */
    allOpen: boolean;
}>();

defineEmits<{ choose: [id: number | string]; toggle: [id: number | string] }>();

const key = computed(() => String(props.node.id));
const children = computed(() => props.node.children ?? []);
const open = computed(() => props.allOpen || props.expanded.has(key.value));
</script>

<template>
    <li role="treeitem" :aria-expanded="children.length ? open : undefined" :aria-selected="selected.has(key)">
        <div class="flex items-center gap-1 rounded px-1 py-1 hover:bg-surface" :style="{ paddingInlineStart: `${depth * 1.25 + 0.25}rem` }">
            <button v-if="children.length" type="button" class="rounded p-0.5 text-muted hover:text-ink" :aria-label="open ? t('Collapse') : t('Expand')" @click="$emit('toggle', node.id)">
                <ChevronRight class="size-4 transition-transform rtl:hidden" :class="{ 'rotate-90': open }" />
                <ChevronLeft class="size-4 transition-transform ltr:hidden" :class="{ '-rotate-90': open }" />
            </button>
            <span v-else class="w-5 shrink-0" />
            <label class="flex min-w-0 flex-1 items-center gap-2 text-sm" :class="disabled.has(key) ? 'cursor-not-allowed text-muted' : 'cursor-pointer'">
                <input
                    :type="multiple ? 'checkbox' : 'radio'"
                    :name="multiple ? undefined : group"
                    :checked="selected.has(key)"
                    :disabled="disabled.has(key)"
                    class="accent-brand"
                    @change="$emit('choose', node.id)"
                />
                <span class="truncate" :class="{ 'font-medium': selected.has(key) }">{{ node.label }}</span>
                <span v-if="children.length" class="ms-auto rounded bg-surface px-1.5 text-xs text-muted tabular-nums">{{ children.length }}</span>
            </label>
        </div>
        <ul v-if="children.length && open" role="group">
            <TreeSelectNode
                v-for="child in children"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
                :multiple="multiple"
                :group="group"
                :selected="selected"
                :disabled="disabled"
                :expanded="expanded"
                :all-open="allOpen"
                @choose="$emit('choose', $event)"
                @toggle="$emit('toggle', $event)"
            />
        </ul>
    </li>
</template>
