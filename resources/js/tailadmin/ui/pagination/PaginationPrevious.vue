<script setup lang="ts">
import { useAttrs } from 'vue'
import type { ButtonSize, ButtonVariant } from '@/tailadmin/ui/button'
import { ChevronLeft as ChevronLeftIcon} from '@/lib/icons'
import PaginationLink from './PaginationLink.vue'

defineOptions({ inheritAttrs: false })

withDefaults(
  defineProps<{
    text?: string
    size?: ButtonSize
    variant?: ButtonVariant
    disabled?: boolean
  }>(),
  {
    text: 'Previous',
    size: 'default',
  },
)

const attrs = useAttrs()
</script>

<template>
  <PaginationLink
    aria-label="Go to previous page"
    :size="size"
    :variant="variant"
    :disabled="disabled"
    v-bind="attrs"
  >
    <slot>
      <ChevronLeftIcon data-icon="inline-start" class="rtl:rotate-180" />
      <span v-if="size !== 'icon'" class="hidden sm:block">{{ text }}</span>
    </slot>
  </PaginationLink>
</template>
