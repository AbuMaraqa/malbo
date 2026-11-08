<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import RichEditor from '@/Components/form/RichEditor.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface PageForm {
    title: string;
    body: string;
    meta_description: string;
    slug: string;
    is_published: boolean;
    show_in_footer: boolean;
    sort_order: number | string;
}

defineProps<{ form: InertiaForm<PageForm>; isSystem?: boolean }>();
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
            <div class="space-y-4">
                <TextInput v-model="form.title" :label="t('Title')" required :maxlength="160" :error="form.errors.title" />
                <RichEditor v-model="form.body" :label="t('Page text')" :error="form.errors.body" />
                <TextareaInput
                    v-model="form.meta_description"
                    :label="t('Search engine description')"
                    :hint="t('One or two sentences shown under the page title on Google.')"
                    :rows="2"
                    :maxlength="300"
                    :error="form.errors.meta_description"
                />
            </div>
        </Card>

        <Card>
            <div class="space-y-4">
                <TextInput
                    v-model="form.slug"
                    :label="t('Link')"
                    dir="ltr"
                    :maxlength="120"
                    :disabled="isSystem"
                    :hint="isSystem ? t('Store policy links do not change.') : t('Letters, numbers and dashes. Left empty, it is made from the title.')"
                    :error="form.errors.slug"
                />
                <Toggle v-model="form.is_published" :label="t('Published')" />
                <Toggle v-model="form.show_in_footer" :label="t('Show in the store footer')" />
                <TextInput v-model="form.sort_order" :label="t('Sort Order')" type="number" min="0" max="10000" :error="form.errors.sort_order" />
            </div>
        </Card>
    </div>
</template>
