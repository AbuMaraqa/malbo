<script setup lang="ts">
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <TextInput v-model="block.content.heading" :label="t('Heading')" :maxlength="160" :error="error('content.heading')" />
        <TextInput
            v-model="block.settings.ends_at"
            :label="t('Ends at')"
            type="datetime-local"
            required
            :hint="t('The block disappears once this time has passed.')"
            :error="error('settings.ends_at')"
        />
        <TextareaInput v-model="block.content.text" class="md:col-span-2" :label="t('Text')" :rows="2" :maxlength="1000" :error="error('content.text')" />
    </div>
</template>
