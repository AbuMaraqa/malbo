<script setup lang="ts">
import { t } from '@/lib/i18n';

import Button from '../ui/Button.vue';

defineProps<{ processing?: boolean; cancelHref?: string; submitLabel?: string }>();
</script>

<template>
    <!-- Stays on the bottom edge of the screen: short forms grow to fill the page (app.css), long ones scroll under it. -->
    <div data-form-actions class="sticky bottom-0 z-10 -mx-4 -mb-6 mt-auto flex items-center gap-2 border-t border-line bg-card/90 px-4 py-3 backdrop-blur-md md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">

        <Button type="submit" variant="primary" :loading="processing">{{ submitLabel ?? t('Save') }}</Button>
        <Button v-if="cancelHref" :href="cancelHref">{{ t('Cancel') }}</Button>
    </div>
</template>
