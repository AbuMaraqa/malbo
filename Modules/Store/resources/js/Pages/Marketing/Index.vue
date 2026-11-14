<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import FormActions from '@/Components/form/FormActions.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { Copy } from '@/lib/icons';
import { route } from '@/lib/route';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    settings: {
        pixel_enabled: boolean;
        meta_pixel_id: string | null;
        capi_enabled: boolean;
        has_capi_token: boolean;
        meta_test_event_code: string | null;
        feed_token: string | null;
        cart_reminders_enabled: boolean;
        abandoned_after_minutes: number;
        reminder_delays: number[];
    };
    feed: { xml: string; csv: string };
    limits: { min: number; max: number };
}>();

const form = useForm({
    pixel_enabled: props.settings.pixel_enabled,
    meta_pixel_id: props.settings.meta_pixel_id ?? '',
    capi_enabled: props.settings.capi_enabled,
    // Write-only: empty keeps the saved token.
    meta_capi_token: '',
    forget_capi_token: false,
    meta_test_event_code: props.settings.meta_test_event_code ?? '',
    feed_token: props.settings.feed_token ?? '',
    cart_reminders_enabled: props.settings.cart_reminders_enabled,
    abandoned_after_minutes: props.settings.abandoned_after_minutes as number | string,
    first_delay: (props.settings.reminder_delays[0] ?? 60) as number | string,
    second_delay: (props.settings.reminder_delays[1] ?? '') as number | string,
});

const copied = ref<string | null>(null);
// The server reports the delays as `reminder_delays.N`, fields the form does not have.
const delayErrors = computed(() => form.errors as Record<string, string | undefined>);

function generateToken(): void {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    form.feed_token = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

async function copy(text: string): Promise<void> {
    await navigator.clipboard?.writeText(text);
    copied.value = text;
}

function save(): void {
    form.transform((data) => ({
        pixel_enabled: data.pixel_enabled,
        meta_pixel_id: data.meta_pixel_id.trim() || null,
        capi_enabled: data.capi_enabled,
        meta_capi_token: data.meta_capi_token.trim() || null,
        forget_capi_token: data.forget_capi_token,
        meta_test_event_code: data.meta_test_event_code.trim() || null,
        feed_token: data.feed_token.trim() || null,
        cart_reminders_enabled: data.cart_reminders_enabled,
        abandoned_after_minutes: data.abandoned_after_minutes,
        reminder_delays: [data.first_delay, data.second_delay].filter((delay) => delay !== '' && delay !== null),
    })).put(route('marketing.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('meta_capi_token', 'forget_capi_token'),
    });
}
</script>

<template>
    <PageHeader :title="t('Marketing settings')" :description="t('Meta Pixel and Conversions API, the product feed for Facebook and Instagram, and abandoned cart reminders.')" />

    <form class="grid gap-6" @submit.prevent="save">
        <Card :title="t('Meta Pixel and Conversions API')" :description="t('Events load only after a visitor accepts marketing cookies, and never on the admin or the wholesale portal.')">
            <div class="grid gap-5 md:grid-cols-2">
                <TextInput v-model="form.meta_pixel_id" :label="t('Pixel ID')" dir="ltr" :maxlength="20" :error="form.errors.meta_pixel_id" />
                <TextInput
                    v-model="form.meta_test_event_code"
                    :label="t('Test event code')"
                    :hint="t('Only while testing in Events Manager. Leave empty for live events.')"
                    dir="ltr"
                    :maxlength="40"
                    :error="form.errors.meta_test_event_code"
                />
                <Toggle v-model="form.pixel_enabled" :label="t('Browser Pixel')" :hint="t('Loads the Meta Pixel on storefront pages.')" />
                <Toggle v-model="form.capi_enabled" :label="t('Conversions API')" :hint="t('Sends the same events from the server, with the same ids.')" />
                <div class="md:col-span-2">
                    <TextInput
                        v-model="form.meta_capi_token"
                        type="password"
                        autocomplete="off"
                        :label="t('Conversions API token')"
                        :placeholder="settings.has_capi_token ? t('A token is saved. Type a new one to replace it.') : ''"
                        dir="ltr"
                        :error="form.errors.meta_capi_token"
                    />
                    <label v-if="settings.has_capi_token" class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="form.forget_capi_token" type="checkbox" class="size-4 rounded border-line" />
                        {{ t('Remove the saved token') }}
                    </label>
                </div>
            </div>
        </Card>

        <Card :title="t('Product feed')" :description="t('Add this address as a data source in Meta Commerce Manager. It updates every hour.')">
            <div class="grid gap-4">
                <div v-for="link in [{ label: 'XML', url: feed.xml }, { label: 'CSV', url: feed.csv }]" :key="link.label" class="flex flex-wrap items-center gap-2">
                    <span class="w-12 text-sm font-medium text-muted">{{ link.label }}</span>
                    <code class="min-w-0 flex-1 truncate rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink" dir="ltr">{{ link.url }}</code>
                    <Button type="button" size="sm" @click="copy(link.url)"><Copy class="size-4" />{{ copied === link.url ? t('Copied') : t('Copy') }}</Button>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <TextInput
                        v-model="form.feed_token"
                        class="min-w-64 flex-1"
                        :label="t('Feed token')"
                        :hint="t('When set, the feed only opens with it. Save to update the addresses above.')"
                        dir="ltr"
                        :maxlength="64"
                        :error="form.errors.feed_token"
                    />
                    <Button type="button" @click="generateToken">{{ t('Generate') }}</Button>
                </div>
            </div>
        </Card>

        <Card :title="t('Abandoned cart reminders')" :description="t('Only buyers who ticked \'Remind me about my cart\' at checkout get reminders, by email, or by text message without an email.')">
            <div class="grid gap-5 md:grid-cols-3">
                <div class="md:col-span-3">
                    <Toggle v-model="form.cart_reminders_enabled" :label="t('Send reminders')" />
                </div>
                <TextInput
                    v-model="form.abandoned_after_minutes"
                    type="number"
                    :min="limits.min"
                    :max="limits.max"
                    :label="t('Abandoned after (minutes)')"
                    :error="form.errors.abandoned_after_minutes"
                />
                <TextInput
                    v-model="form.first_delay"
                    type="number"
                    :min="limits.min"
                    :max="limits.max"
                    :label="t('First reminder after (minutes)')"
                    :error="delayErrors['reminder_delays.0'] ?? delayErrors.reminder_delays"
                />
                <TextInput
                    v-model="form.second_delay"
                    type="number"
                    :min="limits.min"
                    :max="limits.max"
                    :label="t('Second reminder after (minutes)')"
                    :hint="t('Leave empty for one reminder')"
                    :error="delayErrors['reminder_delays.1']"
                />
            </div>
        </Card>

        <FormActions :processing="form.processing" />
    </form>
</template>
