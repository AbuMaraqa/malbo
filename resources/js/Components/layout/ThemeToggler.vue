<script setup lang="ts">
import { Moon, Sun } from '@/lib/icons';

import { t } from '@/lib/i18n';
import { useTheme } from '@/tailadmin/composables/useTheme';
import { Button } from '@/tailadmin/ui/button';

const { toggleTheme, isDarkMode } = useTheme();
</script>

<template>
    <Button variant="outline" size="icon-lg" :aria-label="t('Toggle theme')" @click="toggleTheme">
        <Sun v-if="isDarkMode" />
        <Moon v-else />
    </Button>
</template>
