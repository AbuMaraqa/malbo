<script setup lang="ts">
import RichEditor from '@/Components/form/RichEditor.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <RichEditor v-model="block.content.html" :label="t('Text')" :error="error('content.html')" />
</template>
