<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import SelectInput from '@/Components/form/SelectInput.vue';
import Field from '@/Components/form/Field.vue';
import { t } from '@/lib/i18n';

import type { BusinessType, SignUpForm, SlugState } from './signUpTypes';

defineProps<{
    form: SignUpForm;
    baseDomain: string;
    businessTypes: BusinessType[];
    slugState: SlugState;
    slugMessage: string | null;
}>();

const emit = defineEmits<{ slugInput: [value: string] }>();
</script>

<template>
    <div class="space-y-4">
        <TextInput v-model="form.storeName" :label="t('Store name')" required :maxlength="120" autocomplete="organization" :error="form.errors.storeName" />

        <Field id="slug" :label="t('Store address')" required :error="form.errors.slug ?? (slugState === 'unavailable' ? (slugMessage ?? undefined) : undefined)" :hint="t('English letters and digits, with single hyphens. This becomes your store link.')">
            <div class="flex rounded-md border border-gray-300 focus-within:border-store-600" dir="ltr">
                <input
                    id="slug"
                    :value="form.slug"
                    type="text"
                    maxlength="30"
                    autocapitalize="none"
                    spellcheck="false"
                    class="block w-full min-w-0 rounded-s-md bg-transparent px-3 py-2 text-sm outline-none"
                    @input="emit('slugInput', ($event.target as HTMLInputElement).value)"
                />
                <span class="flex items-center rounded-e-md border-s border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">.{{ baseDomain }}</span>
            </div>
        </Field>
        <p v-if="slugState === 'available' && !form.errors.slug" class="text-sm text-green-700" dir="ltr">{{ form.slug }}.{{ baseDomain }}</p>
        <p v-else-if="slugState === 'checking'" class="text-sm text-gray-500">{{ t('Checking the address…') }}</p>

        <SelectInput v-model="form.businessType" :label="`${t('What do you sell?')} (${t('optional')})`" :options="businessTypes" placeholder="—" :error="form.errors.businessType" />
    </div>
</template>
