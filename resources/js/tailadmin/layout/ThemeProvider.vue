<template>
  <slot></slot>
</template>

<script setup lang="ts">
import { useThemeProvider } from '@/tailadmin/composables/useTheme'

useThemeProvider()
</script>
