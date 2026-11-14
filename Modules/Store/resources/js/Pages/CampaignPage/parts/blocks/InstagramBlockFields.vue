<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <TextInput v-model="block.content.heading" :label="t('Heading')" :maxlength="160" :error="error('content.heading')" />
        <TextInput
            v-model="block.settings.limit"
            :label="t('Posts shown')"
            type="number"
            min="1"
            max="24"
            :hint="t('The newest active posts of the Instagram page in the admin.')"
            :error="error('settings.limit')"
        />
    </div>
</template>
