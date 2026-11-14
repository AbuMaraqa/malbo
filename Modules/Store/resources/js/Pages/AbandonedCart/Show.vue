<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: AdminLayout });

defineProps<{
    cart: {
        id: number;
        customer: string | null;
        email: string | null;
        phone: string | null;
        consent_at: string | null;
        locale: string;
        value: string;
        created_at: string | null;
        last_activity_at: string | null;
        recovered: boolean;
        order: { number: string; url: string } | null;
    };
    lines: {
        id: number;
        name: string;
        options: { name: string; value: string }[];
        image: string | null;
        sku: string | null;
        quantity: number;
        price: string;
        total: string;
        available: boolean;
        problem: string | null;
    }[];
    reminders: { sequence: number; channel: string; recipient: string; status: string; sent_at: string | null }[];
}>();

const statusTone: Record<string, 'success' | 'danger' | 'warning'> = { sent: 'success', failed: 'danger', sending: 'warning' };
const statusLabel = (status: string): string => (status === 'sent' ? t('Sent') : status === 'failed' ? t('Failed') : t('Sending'));
</script>

<template>
    <PageHeader :title="t('Abandoned cart #:id', { id: cart.id })" :parents="[{ title: t('Abandoned carts'), href: route('abandoned-carts.index') }]" />

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <Card :title="t('Items')" :description="t('At today\'s prices. Items that cannot be ordered now are not counted.')">
            <ul class="divide-y divide-line">
                <li v-for="line in lines" :key="line.id" class="flex items-center gap-4 py-3">
                    <img v-if="line.image" :src="line.image" alt="" class="size-14 shrink-0 rounded-lg border border-line object-cover" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink">{{ line.name }}</p>
                        <p v-if="line.options.length" class="text-sm text-muted">{{ line.options.map((option) => `${option.name}: ${option.value}`).join(' · ') }}</p>
                        <p v-if="line.sku" class="text-xs text-muted" dir="ltr">{{ line.sku }}</p>
                        <p v-if="!line.available" class="text-sm text-error-600 dark:text-error-400">{{ line.problem }}</p>
                    </div>
                    <div class="text-end text-sm">
                        <p class="text-muted">{{ line.quantity }} × {{ line.price }}</p>
                        <p class="font-semibold text-ink">{{ line.total }}</p>
                    </div>
                </li>
            </ul>
            <template #footer>
                <span class="text-sm text-muted">{{ t('Value') }}</span>
                <strong class="text-ink">{{ cart.value }}</strong>
            </template>
        </Card>

        <div class="grid content-start gap-6">
            <Card :title="t('Contact')">
                <dl class="grid gap-3 text-sm">
                    <div v-if="cart.customer">
                        <dt class="text-muted">{{ t('Customer') }}</dt>
                        <dd class="font-medium text-ink">{{ cart.customer }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Email') }}</dt>
                        <dd class="font-medium text-ink" dir="ltr">{{ cart.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Phone') }}</dt>
                        <dd class="font-medium text-ink" dir="ltr">{{ cart.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Agreed to reminders') }}</dt>
                        <dd class="text-ink">{{ cart.consent_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Last activity') }}</dt>
                        <dd class="text-ink">{{ cart.last_activity_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Language') }}</dt>
                        <dd class="text-ink">{{ cart.locale }}</dd>
                    </div>
                </dl>
                <div v-if="cart.order" class="mt-4 flex flex-wrap items-center gap-2">
                    <Badge :tone="cart.recovered ? 'success' : 'neutral'" dot>{{ cart.recovered ? t('Recovered') : t('Ordered') }}</Badge>
                    <Button size="sm" :href="cart.order.url">{{ t('Order :number', { number: cart.order.number }) }}</Button>
                </div>
            </Card>

            <Card :title="t('Reminders')">
                <p v-if="!reminders.length" class="text-sm text-muted">{{ t('No reminder sent yet.') }}</p>
                <ol v-else class="grid gap-3 text-sm">
                    <li v-for="reminder in reminders" :key="reminder.sequence" class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-ink">{{ t('Reminder :number', { number: reminder.sequence }) }} · {{ reminder.channel === 'sms' ? t('Text message') : t('Email') }}</p>
                            <p class="text-muted" dir="ltr">{{ reminder.recipient }}</p>
                            <p class="text-muted">{{ reminder.sent_at }}</p>
                        </div>
                        <Badge :tone="statusTone[reminder.status] ?? 'neutral'">{{ statusLabel(reminder.status) }}</Badge>
                    </li>
                </ol>
            </Card>
        </div>
    </div>
</template>
