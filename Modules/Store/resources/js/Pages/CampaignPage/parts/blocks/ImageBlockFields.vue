<script setup lang="ts">
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';
import BlockImageField from './BlockImageField.vue';
import BlockLinkField from './BlockLinkField.vue';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <BlockImageField :block="block" :error="error" />
        <div class="space-y-4">
            <TextInput v-model="block.content.alt" :label="t('What the picture shows')" :maxlength="160" :error="error('content.alt')" />
            <TextInput v-model="block.content.caption" :label="t('Caption')" :maxlength="300" :error="error('content.caption')" />
            <BlockLinkField :block="block" :error="error" />
        </div>
    </div>
</template>
