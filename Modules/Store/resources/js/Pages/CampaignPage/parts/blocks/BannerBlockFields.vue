<script setup lang="ts">
import TextareaInput from '@/Components/form/TextareaInput.vue';
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
            <TextInput v-model="block.content.heading" :label="t('Heading')" :maxlength="160" :error="error('content.heading')" />
            <TextareaInput v-model="block.content.text" :label="t('Text')" :rows="2" :maxlength="1000" :error="error('content.text')" />
            <TextInput v-model="block.content.button_label" :label="t('Button text')" :maxlength="60" :error="error('content.button_label')" />
            <BlockLinkField :block="block" :error="error" />
        </div>
    </div>
</template>
