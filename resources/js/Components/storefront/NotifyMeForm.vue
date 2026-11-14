<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { Bell, Check } from '@/lib/icons';
import { route } from '@/lib/route';

import HoneypotField from './HoneypotField.vue';
import StoreField from './StoreField.vue';
import { btnDark, fieldError } from './styles';

/**
 * "Notify me when available", under an item that cannot be ordered now: an email or a phone, sent
 * for the chosen variant (or the product itself without variants). We write once, when it is back.
 */
const props = defineProps<{ productId: number }>();

const form = useForm({ product_id: props.productId, email: '', phone: '', website: '' });
const done = ref(false);
const ready = computed(() => form.email.trim() !== '' || form.phone.trim() !== '');

watch(
    () => props.productId,
    (id) => {
        form.product_id = id;
        done.value = false;
    },
);

function submit(): void {
    if (!ready.value || form.processing) return;

    form.post(route('store.stock-alerts.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            done.value = true;
            form.reset('email', 'phone');
        },
    });
}
</script>

<template>
    <section class="mt-6 rounded-lg border border-line bg-(--sand-light) p-5" aria-labelledby="notify-title">
        <p v-if="done" role="status" class="flex items-center gap-2 font-semibold text-[#15803d]">
            <Check class="size-5 shrink-0" aria-hidden="true" />
            {{ t('Done. We will write to you once, as soon as it is back.') }}
        </p>
        <form v-else novalidate @submit.prevent="submit">
            <h2 id="notify-title" class="flex items-center gap-2 font-bold"><Bell class="size-5" aria-hidden="true" />{{ t('Notify me when available') }}</h2>
            <p class="mt-1 text-sm text-(--ink-soft)">{{ t('Leave your email or your phone. Nothing else, no account needed.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" :maxlength="160" :error="form.errors.email" />
                <StoreField v-model="form.phone" :label="t('Phone')" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" :maxlength="40" :error="form.errors.phone" />
            </div>
            <HoneypotField v-model="form.website" />
            <p v-if="form.errors.product_id" role="alert" :class="fieldError">{{ form.errors.product_id }}</p>
            <button type="submit" class="mt-4 w-full sm:w-auto" :class="btnDark" :disabled="!ready || form.processing">{{ t('Notify me') }}</button>
        </form>
    </section>
</template>
