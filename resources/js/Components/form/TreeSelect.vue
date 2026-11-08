<script setup lang="ts">
/**
 * Choose one node of a tree, or many with `multiple`, with a search box and expandable branches.
 *
 *   <TreeSelect v-model="form.parent_id" :nodes="parents" :label="t('Parent Category')" :error="form.errors.parent_id" />
 *
 * - `nodes` is `{ id, label, children? }[]`, at any depth.
 * - `v-model` is one id or null, or an array of ids with `multiple`.
 * - `disabledIds` are shown but cannot be chosen.
 * - Branches holding the current choice start open; a search shows matches with their parents.
 */
import { X } from '@/lib/icons';
import { computed, ref, useId } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';
import TreeSelectNode, { type TreeNode } from './TreeSelectNode.vue';

const props = defineProps<{
    nodes: TreeNode[];
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    multiple?: boolean;
    placeholder?: string;
    disabledIds?: (string | number)[];
}>();

const model = defineModel<(string | number)[] | string | number | null>();
const id = useId();
const search = ref('');

const selected = computed<(string | number)[]>(() => {
    if (Array.isArray(model.value)) return model.value;

    return model.value === null || model.value === undefined || model.value === '' ? [] : [model.value];
});

const selectedKeys = computed(() => new Set(selected.value.map(String)));
const disabledKeys = computed(() => new Set((props.disabledIds ?? []).map(String)));

function ancestorsOfSelection(nodes: TreeNode[], found = new Set<string>()): boolean {
    let holds = false;

    for (const node of nodes) {
        const below = ancestorsOfSelection(node.children ?? [], found);

        if (below) found.add(String(node.id));
        if (below || selectedKeys.value.has(String(node.id))) holds = true;
    }

    return holds;
}

function initialExpanded(): Set<string> {
    const found = new Set<string>();
    ancestorsOfSelection(props.nodes, found);

    return found;
}

const expanded = ref(initialExpanded());

function matching(nodes: TreeNode[], term: string): TreeNode[] {
    return nodes.flatMap((node) => {
        if (node.label.toLowerCase().includes(term)) return [node];

        const children = matching(node.children ?? [], term);

        return children.length ? [{ ...node, children }] : [];
    });
}

const term = computed(() => search.value.trim().toLowerCase());
const visible = computed(() => (term.value ? matching(props.nodes, term.value) : props.nodes));

function find(nodes: TreeNode[], key: string): TreeNode | undefined {
    for (const node of nodes) {
        if (String(node.id) === key) return node;

        const below = find(node.children ?? [], key);

        if (below) return below;
    }

    return undefined;
}

const labelOf = (value: string | number): string => find(props.nodes, String(value))?.label ?? String(value);

function choose(value: string | number): void {
    if (!props.multiple) {
        model.value = value;

        return;
    }

    model.value = selectedKeys.value.has(String(value)) ? selected.value.filter((item) => String(item) !== String(value)) : [...selected.value, value];
}

function clear(value: string | number): void {
    model.value = props.multiple ? selected.value.filter((item) => String(item) !== String(value)) : null;
}

function toggleBranch(value: string | number): void {
    const key = String(value);
    const next = new Set(expanded.value);

    if (next.has(key)) next.delete(key);
    else next.add(key);

    expanded.value = next;
}
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <div class="rounded-control border bg-card" :class="error ? 'border-danger' : 'border-line'">
            <div class="flex flex-wrap items-center gap-1 border-b border-line px-2 py-1.5">
                <span v-for="value in selected" :key="value" class="inline-flex items-center gap-1 rounded bg-surface px-1.5 py-0.5 text-sm">
                    {{ labelOf(value) }}
                    <button type="button" class="text-muted hover:text-ink" :aria-label="t('Remove')" @click="clear(value)"><X class="size-3" /></button>
                </span>
                <input :id="id" v-model="search" type="search" :placeholder="selected.length ? t('Search...') : (placeholder ?? t('Search...'))" class="h-7 min-w-24 flex-1 bg-transparent text-sm outline-none" />
            </div>
            <ul role="tree" :aria-multiselectable="multiple" class="max-h-64 overflow-y-auto p-1">
                <TreeSelectNode
                    v-for="node in visible"
                    :key="node.id"
                    :node="node"
                    :depth="0"
                    :multiple="multiple"
                    :group="id"
                    :selected="selectedKeys"
                    :disabled="disabledKeys"
                    :expanded="expanded"
                    :all-open="!!term"
                    @choose="choose"
                    @toggle="toggleBranch"
                />
                <li v-if="!visible.length" class="px-2 py-3 text-center text-sm text-muted">{{ t('No results') }}</li>
            </ul>
        </div>
    </Field>
</template>
