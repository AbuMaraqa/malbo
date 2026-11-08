<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';
import { route } from '@/lib/route';

import HoneypotField from './HoneypotField.vue';
import StoreField from './StoreField.vue';
import { btnBrand, glassStrong } from './styles';

/** Name, phone and an optional email. The server says whether this is a new member. */
const page = usePage();
const form = useForm({ name: '', phone: '', email: '', website: '' });
const result = ref<string | null>(null);

function join(): void {
    form.post(route('store.members.join'), {
        preserveScroll: true,
        onSuccess: () => {
            result.value = (page.props.flash as { success?: string | null }).success ?? null;
            form.reset();
        },
    });
}
</script>

<template>
    <div data-members-form>
        <div v-if="result" role="status" class="rounded-2xl p-7 text-center" :class="glassStrong">
            <span aria-hidden="true" class="mx-auto grid size-14 place-items-center rounded-full bg-store text-store-contrast"><Check class="size-7" /></span>
            <p class="mt-4 text-xl font-bold">{{ result }}</p>
            <p class="mt-1.5 leading-7 text-(--ink-soft)">{{ t('We will message you first about new arrivals and members-only offers.') }}</p>
        </div>
        <form v-else class="grid gap-4 rounded-2xl p-6 sm:p-7" :class="glassStrong" novalidate @submit.prevent="join">
            <StoreField v-model="form.name" :label="t('Name')" autocomplete="name" required :maxlength="120" :error="form.errors.name" />
            <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
            <StoreField v-model="form.email" :label="t('Email')" :hint="t('optional')" type="email" autocomplete="email" dir="ltr" :maxlength="160" :error="form.errors.email" />
            <HoneypotField v-model="form.website" />
            <button type="submit" class="mt-1 w-full" :class="btnBrand" :disabled="form.processing">{{ form.processing ? t('Sending…') : t('Join now') }}</button>
            <p class="text-center text-[13px] text-muted">{{ t('Free. We only message you about what matters, and you can leave any time.') }}</p>
        </form>
    </div>
</template>
