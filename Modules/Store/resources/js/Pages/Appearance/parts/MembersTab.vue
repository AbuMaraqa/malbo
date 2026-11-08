<script setup lang="ts">
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

import LocalizedField from './LocalizedField.vue';
import type { AppearanceForm } from './types';

defineProps<{ form: AppearanceForm }>();
</script>

<template>
    <div class="space-y-6">
        <Toggle
            v-model="form.membership_enabled"
            :label="t('Show the members section on the home page')"
            :hint="t('Visitors leave their name and phone to join; you find them under Online store > Members.')"
        />
        <LocalizedField v-model="form.membership_title" :form="form" name="membership_title" :label="t('Members section title')" :hint="t('Left empty: “Join the store circle”.')" />
        <LocalizedField
            v-model="form.membership_text"
            :form="form"
            name="membership_text"
            :label="t('Members section text')"
            textarea
            :hint="t('Left empty: early access to new arrivals and members-only offers.')"
        />
    </div>
</template>
