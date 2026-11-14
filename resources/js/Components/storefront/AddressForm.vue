<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import StoreField from './StoreField.vue';
import StoreSelect from './StoreSelect.vue';
import { btnBrand, btnOutline, fieldError } from './styles';
import type { DeliverableCity, SavedAddress } from './types';

/** Adds an address to the account, or edits one. Only cities the store delivers to are offered. */
const props = defineProps<{ cities: DeliverableCity[]; address?: SavedAddress | null }>();
const emit = defineEmits<{ done: []; cancel: [] }>();

const form = useForm({
    label: props.address?.label ?? '',
    recipient_name: props.address?.recipient_name ?? '',
    phone: props.address?.phone ?? '',
    city_id: props.address?.city_id ?? (null as number | null),
    district_id: props.address?.district_id ?? (null as number | null),
    address_line: props.address?.address_line ?? '',
    is_default: props.address?.is_default ?? false,
});

/** Errors that belong to no field (the address limit). */
const otherErrors = computed(() => form.errors as Record<string, string | undefined>);

const districts = computed(() => props.cities.find((city) => city.value === form.city_id)?.districts ?? []);

watch(
    () => form.city_id,
    () => {
        if (!districts.value.some((district) => district.value === form.district_id)) form.district_id = null;
    },
);

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => emit('done') };

    if (props.address) {
        form.put(route('store.account.addresses.update', props.address.id), options);
    } else {
        form.post(route('store.account.addresses.store'), options);
    }
}
</script>

<template>
    <form class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="submit">
        <StoreField v-model="form.label" :label="t('Address name')" :hint="t('optional, e.g. Home')" :maxlength="60" :error="form.errors.label" />
        <StoreField v-model="form.recipient_name" :label="t('Full name')" autocomplete="name" required :maxlength="120" :error="form.errors.recipient_name" />
        <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
        <StoreSelect v-model="form.city_id" :label="t('City')" :placeholder="t('Choose your city')" :options="cities" required :error="form.errors.city_id" />
        <StoreSelect
            v-if="districts.length"
            v-model="form.district_id"
            :label="t('Area')"
            :placeholder="t('Choose your area')"
            :options="districts"
            required
            :error="form.errors.district_id"
        />
        <StoreField
            v-model="form.address_line"
            class="sm:col-span-2"
            :label="t('Address')"
            :hint="t('street, building, landmark')"
            autocomplete="street-address"
            required
            :maxlength="500"
            :error="form.errors.address_line"
        />
        <label class="flex cursor-pointer items-center gap-2 sm:col-span-2">
            <input v-model="form.is_default" type="checkbox" class="size-4 accent-(--color-store)" />
            <span>{{ t('Use as my default address') }}</span>
        </label>
        <p v-if="otherErrors.address" :class="fieldError" class="sm:col-span-2">{{ otherErrors.address }}</p>
        <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Save address') }}</button>
            <button type="button" :class="btnOutline" @click="emit('cancel')">{{ t('Cancel') }}</button>
        </div>
    </form>
</template>
