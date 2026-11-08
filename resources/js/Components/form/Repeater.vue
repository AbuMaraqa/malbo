<script setup lang="ts" generic="Item extends object">
/**
 * A list of sub-forms: add, remove, drag to reorder, optional clone and collapse.
 *
 *   <Repeater v-model="form.values" name="values" :errors="form.errors" :new-item="() => ({ name: '' })" :add-label="t('Add')">
 *       <template #item="{ item, error }">
 *           <TextInput v-model="item.name" :error="error('name')" />
 *       </template>
 *   </Repeater>
 *
 * - `name` is the field's path in the form. `error('name')` in the slot reads `errors['values.0.name']`;
 *   an error on the list itself (`errors['values']`) is shown under it.
 * - Errors are keyed by position, so they are hidden after an add, remove or drag until the next submit.
 * - `reorder` fires after a drag with the items in their new order, for pages that save the order at
 *   once through their own route. The model is already updated by then.
 * - An item with a `id` keeps it; a clone (`cloneable`) drops it, because the clone is a new row.
 * - `collapsible` shows `itemLabel(item, index)` as the title; `collapsed` starts saved rows closed.
 *   A collapsed row with an error opens by itself.
 */
import { ChevronDown, Copy, GripVertical, Plus, Trash2 } from '@/lib/icons';
import { computed, onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';

import { t } from '@/lib/i18n';

import Button from '../ui/Button.vue';

const props = withDefaults(
    defineProps<{
        /** The field's path in the form, like `values` or `product.options`. */
        name: string;
        /** A new empty row. */
        newItem: () => Item;
        /** `form.errors`. */
        errors?: Record<string, string | undefined>;
        label?: string;
        addLabel?: string;
        emptyText?: string;
        reorderable?: boolean;
        cloneable?: boolean;
        collapsible?: boolean;
        collapsed?: boolean;
        itemLabel?: (item: Item, index: number) => string | null | undefined;
        /** At this many rows the add button goes away. */
        max?: number;
    }>(),
    { errors: () => ({}), reorderable: true },
);

const emit = defineEmits<{ reorder: [items: Item[]] }>();

const model = defineModel<Item[]>({ required: true });

defineSlots<{
    item: (props: { item: Item; index: number; error: (field: string) => string | undefined }) => unknown;
}>();

const list = ref<HTMLElement>();
const stale = ref(false);
const toggled = ref(new Set<number>());

// A stable key per row, so a drag moves the row and its inputs keep their state.
const keys = new WeakMap<object, number>();
let nextKey = 0;

function keyOf(item: Item): number {
    const raw = toRaw(item);
    let key = keys.get(raw);

    if (key === undefined) {
        key = nextKey++;
        keys.set(raw, key);
    }

    return key;
}

watch(
    () => props.errors,
    () => {
        stale.value = false;
    },
);

const listError = computed(() => (stale.value ? undefined : props.errors[props.name]));

function errorFor(index: number): (field: string) => string | undefined {
    return (field) => (stale.value ? undefined : props.errors[`${props.name}.${index}.${field}`]);
}

function hasError(index: number): boolean {
    return !stale.value && Object.keys(props.errors).some((key) => key.startsWith(`${props.name}.${index}.`) && props.errors[key]);
}

function isOpen(item: Item, index: number): boolean {
    if (!props.collapsible || hasError(index)) return true;

    return toggled.value.has(keyOf(item)) === !!props.collapsed;
}

function toggle(item: Item): void {
    const key = keyOf(item);
    const next = new Set(toggled.value);

    if (next.has(key)) next.delete(key);
    else next.add(key);

    toggled.value = next;
}

function changed(items: Item[]): void {
    stale.value = true;
    model.value = items;
}

function add(): void {
    const item = props.newItem();

    if (props.collapsed) {
        toggled.value = new Set(toggled.value).add(keyOf(item));
    }

    changed([...model.value, item]);
}

function remove(index: number): void {
    changed(model.value.filter((_, position) => position !== index));
}

function clone(index: number): void {
    const { id: _id, ...copy } = toRaw(model.value[index]) as Item & { id?: unknown };
    const items = [...model.value];
    items.splice(index + 1, 0, copy as unknown as Item);
    changed(items);
}

let sortable: { destroy: () => void } | null = null;

onMounted(async () => {
    if (!props.reorderable || !list.value) return;

    const { default: Sortable } = await import('sortablejs');

    if (!list.value) return;

    sortable = Sortable.create(list.value, {
        handle: '[data-repeater-handle]',
        animation: 150,
        onEnd: ({ item, from, oldIndex, newIndex }) => {
            if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) return;

            // Vue owns these nodes: put the row back and let the new order re-render the list.
            from.removeChild(item);
            from.insertBefore(item, from.children[oldIndex] ?? null);

            const items = [...model.value];
            items.splice(newIndex, 0, ...items.splice(oldIndex, 1));
            changed(items);
            emit('reorder', items);
        },
    });
});

onBeforeUnmount(() => sortable?.destroy());
</script>

<template>
    <div>
        <p v-if="label" class="mb-2 text-sm font-medium">{{ label }}</p>

        <div ref="list" class="space-y-3">
            <div v-for="(item, index) in model" :key="keyOf(item)" class="rounded-lg border border-line bg-card">
                <div class="flex items-center gap-1 border-b border-line px-2 py-1.5">
                    <button v-if="reorderable" type="button" data-repeater-handle class="cursor-grab rounded p-1 text-muted hover:bg-surface hover:text-ink" :aria-label="t('Drag to reorder')">
                        <GripVertical class="size-4" />
                    </button>
                    <button v-if="collapsible" type="button" class="flex min-w-0 flex-1 items-center gap-1 rounded p-1 text-start text-sm font-medium hover:bg-surface" :aria-expanded="isOpen(item, index)" @click="toggle(item)">
                        <ChevronDown class="size-4 shrink-0 text-muted transition-transform" :class="{ 'rotate-180': isOpen(item, index) }" />
                        <span class="truncate">{{ itemLabel?.(item, index) || t('Untitled') }}</span>
                    </button>
                    <span v-else class="min-w-0 flex-1 truncate px-1 text-sm font-medium">{{ itemLabel?.(item, index) }}</span>
                    <Button v-if="cloneable" variant="ghost" size="icon" :aria-label="t('Duplicate')" @click="clone(index)"><Copy class="size-4" /></Button>
                    <Button variant="ghost" size="icon" :aria-label="t('Remove')" @click="remove(index)"><Trash2 class="size-4" /></Button>
                </div>
                <div v-show="isOpen(item, index)" class="p-3">
                    <slot name="item" :item="item" :index="index" :error="errorFor(index)" />
                </div>
            </div>
        </div>

        <p v-if="!model.length && emptyText" class="rounded-lg border border-dashed border-line px-3 py-4 text-center text-sm text-muted">{{ emptyText }}</p>
        <p v-if="listError" class="mt-1 text-sm text-danger" role="alert">{{ listError }}</p>

        <Button v-if="max === undefined || model.length < max" class="mt-3" size="sm" @click="add"><Plus class="size-4" />{{ addLabel ?? t('Add') }}</Button>
    </div>
</template>
