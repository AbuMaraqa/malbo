<script setup lang="ts">
/** An image or banner block's picture: the saved one is shown until a new one is picked. */
import ImageUpload from '@/Components/form/ImageUpload.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined }>();
</script>

<template>
    <div class="space-y-2">
        <img v-if="block.image_url && !block.image" :src="block.image_url" alt="" class="h-28 rounded-lg border border-line object-cover" />
        <ImageUpload
            v-model="block.image"
            :label="block.image_url ? t('Replace the picture') : t('Picture')"
            :required="!block.image_url"
            :error="error('image')"
        />
    </div>
</template>
