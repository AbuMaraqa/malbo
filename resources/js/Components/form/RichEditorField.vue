<script setup lang="ts">
/**
 * The rich text editor. `tables` adds tables and images (a size guide is a table of measures); those
 * extensions download only for the fields that ask for them.
 */
import type { AnyExtension } from '@tiptap/core';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import {
    Bold,
    Columns3,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Plus,
    Quote,
    Redo2,
    Trash2,
    Undo2,
} from '@/lib/icons';
import { onBeforeUnmount, onMounted, shallowRef, watch, type Component } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';

const props = defineProps<{ label?: string; error?: string; hint?: string; required?: boolean; tables?: boolean }>();

const model = defineModel<string | null>();

const editor = shallowRef<Editor>();
let unmounted = false;

onMounted(async () => {
    const extensions: AnyExtension[] = [StarterKit.configure({ heading: { levels: [2, 3] }, link: false }), Link.configure({ openOnClick: false })];

    if (props.tables) {
        const [{ TableKit }, { Image }] = await Promise.all([import('@tiptap/extension-table'), import('@tiptap/extension-image')]);
        extensions.push(TableKit.configure({ table: { resizable: false } }), Image);
    }

    if (unmounted) return;

    editor.value = new Editor({
        content: model.value ?? '',
        extensions,
        editorProps: { attributes: { class: 'min-h-40 px-3 py-2 text-sm outline-none rich-text' } },
        onUpdate: ({ editor: current }) => {
            model.value = current.isEmpty ? '' : current.getHTML();
        },
    });
});

watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(editor.value.isEmpty && !value)) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => {
    unmounted = true;
    editor.value?.destroy();
});

function setLink(): void {
    const previous = editor.value?.getAttributes('link').href as string | undefined;
    const url = window.prompt(t('Link address'), previous ?? 'https://');

    if (url === null) return;

    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();
    } else {
        editor.value?.chain().focus().setLink({ href: url }).run();
    }
}

function addImage(): void {
    const url = window.prompt(t('Image address'), 'https://');

    if (url) editor.value?.chain().focus().setImage({ src: url }).run();
}

interface Tool {
    icon: Component;
    label: string;
    active: string | [string, object] | null;
    run: () => unknown;
}

const run = (command: (chain: ReturnType<Editor['chain']>) => ReturnType<Editor['chain']>) => () => editor.value && command(editor.value.chain().focus()).run();

const tools: Tool[] = [
    { icon: Bold, label: 'Bold', active: 'bold', run: run((chain) => chain.toggleBold()) },
    { icon: Italic, label: 'Italic', active: 'italic', run: run((chain) => chain.toggleItalic()) },
    { icon: Heading2, label: 'Heading', active: ['heading', { level: 2 }], run: run((chain) => chain.toggleHeading({ level: 2 })) },
    { icon: Heading3, label: 'Subheading', active: ['heading', { level: 3 }], run: run((chain) => chain.toggleHeading({ level: 3 })) },
    { icon: List, label: 'Bullet list', active: 'bulletList', run: run((chain) => chain.toggleBulletList()) },
    { icon: ListOrdered, label: 'Numbered list', active: 'orderedList', run: run((chain) => chain.toggleOrderedList()) },
    { icon: Quote, label: 'Quote', active: 'blockquote', run: run((chain) => chain.toggleBlockquote()) },
    { icon: Link2, label: 'Link', active: 'link', run: setLink },
    { icon: Undo2, label: 'Undo', active: null, run: run((chain) => chain.undo()) },
    { icon: Redo2, label: 'Redo', active: null, run: run((chain) => chain.redo()) },
];

/** Shown only with `tables`: a header row, then rows and columns added or removed where the cursor is. */
const tableTools: Tool[] = [
    { icon: Columns3, label: 'Insert table', active: null, run: run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true })) },
    { icon: Plus, label: 'Add row', active: null, run: run((chain) => chain.addRowAfter()) },
    { icon: Trash2, label: 'Remove row', active: null, run: run((chain) => chain.deleteRow()) },
    { icon: Plus, label: 'Add column', active: null, run: run((chain) => chain.addColumnAfter()) },
    { icon: Trash2, label: 'Remove column', active: null, run: run((chain) => chain.deleteColumn()) },
    { icon: Trash2, label: 'Remove table', active: null, run: run((chain) => chain.deleteTable()) },
    { icon: ImagePlus, label: 'Image', active: null, run: addImage },
];

function isActive(tool: Tool): boolean {
    if (!tool.active || !editor.value) return false;

    return Array.isArray(tool.active) ? editor.value.isActive(tool.active[0], tool.active[1] as Record<string, unknown>) : editor.value.isActive(tool.active);
}
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :required="required">
        <div class="rounded-control border focus-within:border-brand" :class="error ? 'border-danger' : 'border-line'">
            <div v-if="editor" class="flex flex-wrap gap-0.5 border-b border-line p-1">
                <button
                    v-for="tool in tools"
                    :key="tool.label"
                    type="button"
                    class="rounded p-1.5 text-muted hover:bg-surface hover:text-ink"
                    :class="{ 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400': isActive(tool) }"
                    :aria-label="t(tool.label)"
                    :title="t(tool.label)"
                    @click="tool.run"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
                <template v-if="tables">
                    <span class="mx-1 w-px self-stretch bg-line" aria-hidden="true"></span>
                    <button
                        v-for="tool in tableTools"
                        :key="tool.label"
                        type="button"
                        class="flex items-center gap-1 rounded p-1.5 text-xs text-muted hover:bg-surface hover:text-ink"
                        :aria-label="t(tool.label)"
                        :title="t(tool.label)"
                        @click="tool.run"
                    >
                        <component :is="tool.icon" class="size-4" />
                        <span class="hidden sm:inline">{{ t(tool.label) }}</span>
                    </button>
                </template>
            </div>
            <EditorContent
                :editor="editor"
                class="[&_img]:max-w-full [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-line [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-line [&_th]:bg-surface [&_th]:px-2 [&_th]:py-1 [&_th]:text-start"
            />
        </div>
    </Field>
</template>
