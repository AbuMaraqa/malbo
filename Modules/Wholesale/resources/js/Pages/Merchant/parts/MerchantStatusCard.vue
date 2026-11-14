<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import TextareaInput from '@/Components/form/TextareaInput.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { MerchantStatus } from './types';

/** Where the application stands, and the moves staff can make from here, each with a note. */
const props = defineProps<{ merchantId: number; status: MerchantStatus }>();

const form = useForm<{ status: string; note: string }>({ status: '', note: '' });

function move(status: string): void {
    form.status = status;
    form.put(route('merchants.status', props.merchantId), { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <Card :title="t('Status')">
        <div class="grid gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="status.tone" dot>{{ status.label }}</Badge>
                <span v-if="status.approved_at" class="text-sm text-muted">{{ t('Approved on :date', { date: status.approved_at }) }}</span>
            </div>

            <template v-if="can('Merchant Update') && status.next.length">
                <TextareaInput
                    v-model="form.note"
                    :label="t('Note')"
                    :hint="t('Required to reject or suspend, and then sent to the merchant. Any other note stays with the staff.')"
                    :rows="3"
                    :maxlength="2000"
                    :error="form.errors.note || form.errors.status"
                />
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="next in status.next"
                        :key="next.value"
                        :variant="next.needs_note ? 'danger' : 'primary'"
                        size="sm"
                        :loading="form.processing && form.status === next.value"
                        :disabled="form.processing"
                        @click="move(next.value)"
                    >
                        {{ next.label }}
                    </Button>
                </div>
            </template>
            <p v-else-if="!status.next.length" class="text-sm text-muted">{{ t('A rejected application cannot be reopened.') }}</p>
        </div>
    </Card>
</template>
