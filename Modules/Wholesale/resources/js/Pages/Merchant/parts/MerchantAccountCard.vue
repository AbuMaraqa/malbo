<script setup lang="ts">
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { ExternalLink } from '@/lib/icons';

/** The merchant's sign-in account and the commercial register's scan (a link valid a few minutes). */
defineProps<{
    account: { name: string | null; email: string | null; created_at: string | null };
    document: { name: string; url: string } | null;
    commercialRegister: string;
}>();
</script>

<template>
    <Card :title="t('Account and documents')">
        <dl class="grid gap-3 text-sm">
            <div>
                <dt class="text-muted">{{ t('Account') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-white/90">{{ account.name }}</dd>
                <dd class="text-gray-600 dark:text-gray-400" dir="ltr">{{ account.email }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ t('Applied on') }}</dt>
                <dd class="text-gray-800 dark:text-white/90">{{ account.created_at }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ t('Commercial register number') }}</dt>
                <dd class="text-gray-800 dark:text-white/90" dir="ltr">{{ commercialRegister }}</dd>
            </div>
            <div>
                <dt class="text-muted">{{ t('Commercial register document') }}</dt>
                <dd v-if="document">
                    <a :href="document.url" class="inline-flex items-center gap-1.5 font-medium text-brand-600 hover:underline dark:text-brand-400">
                        <ExternalLink class="size-4" />{{ document.name }}
                    </a>
                </dd>
                <dd v-else class="text-muted">{{ t('Not uploaded') }}</dd>
            </div>
        </dl>
    </Card>
</template>
