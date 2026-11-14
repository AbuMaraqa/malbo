<script setup lang="ts">
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';
import BlockLinkField from './BlockLinkField.vue';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <TextInput v-model="block.content.heading" :label="t('Heading')" :maxlength="160" :error="error('content.heading')" />
        <TextInput v-model="block.content.button_label" :label="t('Button text')" required :maxlength="60" :error="error('content.button_label')" />
        <TextareaInput v-model="block.content.text" class="md:col-span-2" :label="t('Text')" :rows="2" :maxlength="1000" :error="error('content.text')" />
        <BlockLinkField class="md:col-span-2" :block="block" :error="error" required />
    </div>
</template>
