<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';
import { route } from '@/lib/route';

import HoneypotField from './HoneypotField.vue';
import StoreField from './StoreField.vue';
import { btnBrand, btnOutline, field, fieldLabel } from './styles';

/** The message form: it lands in the store's admin inbox. */
defineProps<{ topics: { value: string; label: string }[] }>();

const form = useForm({ name: '', phone: '', email: '', topic: '', message: '', website: '' });
const sent = ref(false);

function send(): void {
    form.post(route('store.contact.send'), {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
}
</script>

<template>
    <section data-contact="form" aria-labelledby="form-title" class="min-w-0 rounded-lg border border-line bg-white p-6 sm:p-10">
        <div v-if="sent" role="status" class="py-10 text-center">
            <span aria-hidden="true" class="mx-auto grid size-16 place-items-center rounded-full bg-store-50 text-store-700"><Check class="size-8" /></span>
            <h2 id="form-title" class="mt-5 text-2xl font-bold">{{ t('Thank you, your message has been sent.') }}</h2>
            <p class="mx-auto mt-2 max-w-md leading-7 text-(--ink-soft)">{{ t('We will get back to you as soon as possible.') }}</p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                <Link :href="route('store.shop')" :class="btnBrand">{{ t('Continue shopping') }}</Link>
                <button type="button" :class="btnOutline" @click="sent = false">{{ t('Send another message') }}</button>
            </div>
        </div>
        <template v-else>
            <h2 id="form-title" class="text-2xl font-bold">{{ t('Send us a message') }}</h2>
            <p class="mt-1.5 text-(--ink-soft)">{{ t('Leave a phone number or an email and we will reply.') }}</p>

            <form class="mt-7 grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="send">
                <StoreField v-model="form.name" class="sm:col-span-2" :label="t('Name')" autocomplete="name" required :maxlength="120" :error="form.errors.name" />
                <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" :maxlength="40" :error="form.errors.phone" />
                <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" :maxlength="160" :error="form.errors.email" />
                <div class="sm:col-span-2">
                    <label for="contact-topic" :class="fieldLabel">{{ t('Topic') }}</label>
                    <select id="contact-topic" v-model="form.topic" :class="field">
                        <option value="">{{ t('Choose a topic (optional)') }}</option>
                        <option v-for="topic in topics" :key="topic.value" :value="topic.value">{{ topic.label }}</option>
                    </select>
                    <p v-if="form.errors.topic" class="mt-1.5 text-sm text-(--danger)">{{ form.errors.topic }}</p>
                </div>
                <StoreField v-model="form.message" class="sm:col-span-2" :label="t('Your message')" :rows="6" required :maxlength="2000" :error="form.errors.message" />
                <HoneypotField v-model="form.website" />
                <div class="sm:col-span-2">
                    <button type="submit" class="w-full sm:w-auto" :class="btnBrand" :disabled="form.processing">{{ form.processing ? t('Sending…') : t('Send message') }}</button>
                </div>
            </form>
        </template>
    </section>
</template>
