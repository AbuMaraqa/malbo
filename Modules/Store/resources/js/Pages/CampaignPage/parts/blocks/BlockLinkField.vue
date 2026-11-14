<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined; required?: boolean }>();
</script>

<template>
    <TextInput
        v-model="block.settings.link"
        :label="t('Link')"
        dir="ltr"
        :maxlength="500"
        :required="required"
        placeholder="/ar/shop"
        :hint="t('A page of this store (/ar/shop) or a full https:// address.')"
        :error="error('settings.link')"
    />
</template>
