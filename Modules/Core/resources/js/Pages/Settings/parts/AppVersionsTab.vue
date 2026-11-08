<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import AppVersionItem from './AppVersionItem.vue';
import type { SettingsForm } from './types';

const props = defineProps<{ form: InertiaForm<SettingsForm>; savedCount: number }>();

function errorsOf(index: number): Record<string, string | undefined> {
    const prefix = `app_versions.${index}.`;

    return Object.fromEntries(
        Object.entries(props.form.errors as Record<string, string | undefined>)
            .filter(([key]) => key.startsWith(prefix))
            .map(([key, message]) => [key.slice(prefix.length), message]),
    );
}

function add(): void {
    props.form.app_versions.push({ platform: '', store_url: '', min_version: '', latest_version: '', maintenance_mode: false, message: '' });
}

function remove(index: number): void {
    props.form.app_versions.splice(index, 1);
}
</script>

<template>
    <Card :title="t('Manage Platforms')">
        <div class="space-y-3">
            <p v-if="form.app_versions.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ t('No platforms yet') }}
            </p>
            <AppVersionItem
                v-for="(_, index) in form.app_versions"
                :key="index"
                v-model="form.app_versions[index]"
                :errors="errorsOf(index)"
                :removable="index >= savedCount"
                @remove="remove(index)"
            />
            <Button @click="add">{{ t('Add Platform') }}</Button>
        </div>
    </Card>
</template>
