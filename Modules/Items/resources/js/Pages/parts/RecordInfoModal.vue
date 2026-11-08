<script setup lang="ts">
import { ref, watch } from 'vue';

import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';

interface Activity {
    id: number;
    description: string;
    causer: string | null;
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    created_at: string | null;
    created_ago: string | null;
}

interface Info {
    created_by: string | null;
    created_at: string | null;
    updated_at: string | null;
    activities: Activity[];
}

const props = defineProps<{ title: string; url: string }>();

const open = defineModel<boolean>('open', { required: true });

const info = ref<Info | null>(null);
const failed = ref(false);

watch(open, async (isOpen) => {
    if (!isOpen || info.value) return;

    failed.value = false;

    try {
        const response = await fetch(props.url, { headers: { Accept: 'application/json' } });

        if (!response.ok) throw new Error(String(response.status));

        info.value = (await response.json()) as Info;
    } catch {
        failed.value = true;
    }
});

const pretty = (value: unknown): string => JSON.stringify(value, null, 2);
</script>

<template>
    <Modal v-model:open="open" :title="`${t('Information')} - ${title}`" width="max-w-4xl">
        <p v-if="failed" class="text-sm text-danger" role="alert">{{ t('Could not load this information.') }}</p>
        <p v-else-if="!info" class="text-sm text-muted">{{ t('Loading...') }}</p>
        <template v-else>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted">{{ t('Created By') }}</dt>
                    <dd class="font-medium">{{ info.created_by ?? t('Unknown') }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Created At') }}</dt>
                    <dd class="tabular-nums" dir="ltr">{{ info.created_at }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Updated At') }}</dt>
                    <dd class="tabular-nums" dir="ltr">{{ info.updated_at }}</dd>
                </div>
            </dl>

            <div class="mt-5 flex items-center justify-between">
                <h3 class="text-sm font-semibold">{{ t('Activity Log') }}</h3>
                <span class="text-sm text-muted">{{ t('Activities Count') }} {{ info.activities.length }}</span>
            </div>

            <p v-if="!info.activities.length" class="py-6 text-center text-sm text-muted">{{ t('No activities recorded') }}</p>
            <ul v-else class="mt-2 max-h-96 space-y-2 overflow-y-auto">
                <li v-for="activity in info.activities" :key="activity.id" class="rounded-lg border border-line p-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">{{ activity.description }}</p>
                            <p v-if="activity.causer" class="mt-0.5 text-muted">{{ t('By') }}: {{ activity.causer }}</p>
                        </div>
                        <time class="shrink-0 text-xs text-muted" :title="activity.created_at ?? undefined">{{ activity.created_ago }}</time>
                    </div>
                    <details v-if="activity.old || activity.new" class="mt-2">
                        <summary class="cursor-pointer text-brand-600 dark:text-brand-400">{{ t('Show Details') }}</summary>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2" dir="ltr">
                            <div v-if="activity.old">
                                <strong class="text-xs">{{ t('Old Values') }}</strong>
                                <pre class="mt-1 max-h-32 overflow-auto rounded bg-surface p-2 text-xs">{{ pretty(activity.old) }}</pre>
                            </div>
                            <div v-if="activity.new">
                                <strong class="text-xs">{{ t('New Values') }}</strong>
                                <pre class="mt-1 max-h-32 overflow-auto rounded bg-surface p-2 text-xs">{{ pretty(activity.new) }}</pre>
                            </div>
                        </div>
                    </details>
                </li>
            </ul>
        </template>
    </Modal>
</template>
