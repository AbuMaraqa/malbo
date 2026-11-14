<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import TextareaInput from '@/Components/form/TextareaInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: AdminLayout });

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

const props = defineProps<{
    request: {
        id: number;
        order: {
            id: number | null;
            number: string | null;
            status: string | null;
            customer: string | null;
            phone: string | null;
            sub_total: string | null;
            discount: string | null;
            total: string | null;
            delivery_fee: string | null;
        };
        type: { value: string; label: string };
        status: { value: string; label: string; tone: Tone };
        reason: string;
        customer_note: string | null;
        staff_note: string | null;
        refund_amount: string;
        refunded: boolean;
        refunded_at: string | null;
        created_at: string | null;
        created_by: string | null;
        /** The unit as sold (addons included), its share of the order discount, and what the line gives back. */
        lines: {
            id: number;
            name: string;
            sku: string | null;
            quantity: number;
            unit_price: string | null;
            unit_discount: string | null;
            refund: string | null;
            exchange_for: string | null;
        }[];
        history: { id: number; status: string; tone: Tone; note: string | null; at: string | null; by: string | null }[];
    };
    /** Where the request can go from here. */
    transitions: { value: string; label: string }[];
}>();

const note = ref('');
const moving = ref<string | null>(null);
const statusError = ref<string | null>(null);

const staff = useForm({ staff_note: props.request.staff_note ?? '', refunded: props.request.refunded });

const actionLabels: Record<string, string> = {
    approved: t('Approve'),
    rejected: t('Reject'),
    received: t('Mark received'),
    completed: t('Complete'),
};

async function move(to: string): Promise<void> {
    if (to === 'completed' || to === 'rejected') {
        const ok = await confirm({
            title: to === 'completed' ? t('Complete this request?') : t('Reject this request?'),
            description: to === 'completed' ? t('The items go back into stock, an exchange takes its products from stock, and the order follows.') : t('The buyer is told.'),
            confirmLabel: actionLabels[to],
            tone: to === 'rejected' ? 'danger' : 'primary',
        });
        if (!ok) return;
    }

    moving.value = to;
    statusError.value = null;
    router.put(
        route('return-requests.status', props.request.id),
        { status: to, note: note.value || null },
        {
            preserveScroll: true,
            onSuccess: () => (note.value = ''),
            onError: (errors) => (statusError.value = errors.status ?? null),
            onFinish: () => (moving.value = null),
        },
    );
}

function saveStaff(): void {
    staff.put(route('return-requests.update', props.request.id), { preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="`${t('Return request')} #${request.id}`" :parents="[{ title: t('Return requests'), href: route('return-requests.index') }]">
        <template #actions>
            <Button v-if="request.order.id && can('Order Update')" variant="secondary" :href="route('orders.edit', request.order.id)">{{ t('Open the order') }}</Button>
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
        <div class="grid content-start gap-4">
            <Card :title="t('Request')">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted">{{ t('Order Number') }}</dt>
                        <dd class="font-medium" dir="ltr">{{ request.order.number }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Customer') }}</dt>
                        <dd>{{ request.order.customer }} <span v-if="request.order.phone" class="text-muted" dir="ltr">{{ request.order.phone }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Type') }}</dt>
                        <dd>{{ request.type.label }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Status') }}</dt>
                        <dd><Badge :tone="request.status.tone">{{ request.status.label }}</Badge></dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Reason') }}</dt>
                        <dd>{{ request.reason }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Order Status') }}</dt>
                        <dd>{{ request.order.status }}</dd>
                    </div>
                    <div v-if="request.customer_note" class="sm:col-span-2">
                        <dt class="text-muted">{{ t('Customer note') }}</dt>
                        <dd class="whitespace-pre-line">{{ request.customer_note }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Created at') }}</dt>
                        <dd dir="ltr" class="text-start">{{ request.created_at }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Created by') }}</dt>
                        <dd>{{ request.created_by ?? t('Customer') }}</dd>
                    </div>
                </dl>
            </Card>

            <Card :title="t('Items')">
                <table class="w-full text-sm">
                    <thead class="text-muted">
                        <tr>
                            <th class="pb-2 text-start font-medium">{{ t('Product') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Quantity') }}</th>
                            <th class="pb-2 text-start font-medium">{{ t('Sold for') }}</th>
                            <th v-if="request.type.value === 'return'" class="pb-2 text-start font-medium">{{ t('Discount share') }}</th>
                            <th v-if="request.type.value === 'return'" class="pb-2 text-start font-medium">{{ t('Line refund') }}</th>
                            <th v-if="request.type.value === 'exchange'" class="pb-2 text-start font-medium">{{ t('Exchange for') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="line in request.lines" :key="line.id">
                            <td class="py-2">
                                {{ line.name }}
                                <span v-if="line.sku" class="block text-xs text-muted" dir="ltr">{{ line.sku }}</span>
                            </td>
                            <td class="py-2 tabular-nums">{{ line.quantity }}</td>
                            <td class="py-2 tabular-nums">{{ line.unit_price }}</td>
                            <td v-if="request.type.value === 'return'" class="py-2 tabular-nums">{{ line.unit_discount }}</td>
                            <td v-if="request.type.value === 'return'" class="py-2 tabular-nums">{{ line.refund }}</td>
                            <td v-if="request.type.value === 'exchange'" class="py-2">{{ line.exchange_for }}</td>
                        </tr>
                    </tbody>
                </table>
                <dl v-if="request.type.value === 'return'" class="mt-4 grid gap-1 border-t border-line pt-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-muted">{{ t('Subtotal') }}</dt>
                        <dd class="tabular-nums">{{ request.order.sub_total }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Discount') }}</dt>
                        <dd class="tabular-nums">{{ request.order.discount }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Delivery Fee') }}</dt>
                        <dd class="tabular-nums">{{ request.order.delivery_fee }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Grand Total') }}</dt>
                        <dd class="tabular-nums">{{ request.order.total }}</dd>
                    </div>
                </dl>
            </Card>

            <Card :title="t('Status history')">
                <ol class="grid gap-3">
                    <li v-for="row in request.history" :key="row.id" class="text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Badge :tone="row.tone">{{ row.status }}</Badge>
                            <span class="text-muted"><span dir="ltr">{{ row.at }}</span> · {{ row.by ?? t('Customer') }}</span>
                        </div>
                        <p v-if="row.note" class="mt-1 whitespace-pre-line text-muted">{{ row.note }}</p>
                    </li>
                </ol>
            </Card>
        </div>

        <div class="grid content-start gap-4">
            <Card v-if="transitions.length && can('Return Request Update')" :title="t('Next step')">
                <TextareaInput v-model="note" :label="t('Note')" :hint="t('Only staff see it.')" :rows="3" :maxlength="2000" />
                <p v-if="statusError" role="alert" class="mt-3 text-sm text-error-600 dark:text-error-400">{{ statusError }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="transition in transitions"
                        :key="transition.value"
                        :variant="transition.value === 'rejected' ? 'danger' : 'primary'"
                        :loading="moving === transition.value"
                        :disabled="moving !== null"
                        @click="move(transition.value)"
                    >
                        {{ actionLabels[transition.value] ?? transition.label }}
                    </Button>
                </div>
            </Card>

            <Card :title="t('Refund')">
                <p class="text-2xl font-semibold tabular-nums">{{ request.type.value === 'return' ? request.refund_amount : '—' }}</p>
                <p class="mt-1 text-sm text-muted">{{ t('The units sent back at the price they were sold for, less their share of the order discount. Pay it outside the system, then tick refunded.') }}</p>
                <form class="mt-4 grid gap-4" @submit.prevent="saveStaff">
                    <Toggle v-if="request.type.value === 'return'" v-model="staff.refunded" :label="t('Refunded')" :hint="request.refunded_at ?? undefined" />
                    <p v-if="staff.errors.refunded" class="text-sm text-error-600 dark:text-error-400">{{ staff.errors.refunded }}</p>
                    <TextareaInput v-model="staff.staff_note" :label="t('Staff note')" :hint="t('Only staff see it.')" :rows="3" :maxlength="2000" :error="staff.errors.staff_note" />
                    <div>
                        <Button type="submit" variant="primary" :loading="staff.processing">{{ t('Save') }}</Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>
