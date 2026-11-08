<script setup lang="ts">
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Heading2, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Undo2 } from '@/lib/icons';
import { onBeforeUnmount, watch } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';

defineProps<{ label?: string; error?: string; hint?: string; required?: boolean }>();

const model = defineModel<string | null>();

const editor = useEditor({
    content: model.value ?? '',
    extensions: [StarterKit.configure({ heading: { levels: [2, 3] }, link: false }), Link.configure({ openOnClick: false })],
    editorProps: { attributes: { class: 'min-h-40 px-3 py-2 text-sm outline-none rich-text' } },
    onUpdate: ({ editor }) => {
        model.value = editor.isEmpty ? '' : editor.getHTML();
    },
});

watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(editor.value.isEmpty && !value)) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

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
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :required="required">
        <div class="rounded-control border focus-within:border-brand" :class="error ? 'border-danger' : 'border-line'">
            <div v-if="editor" class="flex flex-wrap gap-0.5 border-b border-line p-1">
                <button v-for="tool in [
                        { icon: Bold, label: 'Bold', active: 'bold', run: () => editor!.chain().focus().toggleBold().run() },
                        { icon: Italic, label: 'Italic', active: 'italic', run: () => editor!.chain().focus().toggleItalic().run() },
                        { icon: Heading2, label: 'Heading', active: ['heading', { level: 2 }], run: () => editor!.chain().focus().toggleHeading({ level: 2 }).run() },
                        { icon: Heading3, label: 'Subheading', active: ['heading', { level: 3 }], run: () => editor!.chain().focus().toggleHeading({ level: 3 }).run() },
                        { icon: List, label: 'Bullet list', active: 'bulletList', run: () => editor!.chain().focus().toggleBulletList().run() },
                        { icon: ListOrdered, label: 'Numbered list', active: 'orderedList', run: () => editor!.chain().focus().toggleOrderedList().run() },
                        { icon: Quote, label: 'Quote', active: 'blockquote', run: () => editor!.chain().focus().toggleBlockquote().run() },
                        { icon: Link2, label: 'Link', active: 'link', run: setLink },
                        { icon: Undo2, label: 'Undo', active: null, run: () => editor!.chain().focus().undo().run() },
                        { icon: Redo2, label: 'Redo', active: null, run: () => editor!.chain().focus().redo().run() },
                    ]"
                    :key="tool.label"
                    type="button"
                    class="rounded p-1.5 text-muted hover:bg-surface hover:text-ink"
                    :class="{ 'bg-brand-50 dark:bg-brand-500/15 text-brand-600 dark:text-brand-400': tool.active && (Array.isArray(tool.active) ? editor.isActive(tool.active[0] as string, tool.active[1] as object) : editor.isActive(tool.active)) }"
                    :aria-label="t(tool.label)"
                    :title="t(tool.label)"
                    @click="tool.run"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
            </div>
            <EditorContent :editor="editor" />
        </div>
    </Field>
</template>
