<script setup lang="ts">
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

defineProps<{
    tabs: { value: string; label: string; hasError?: boolean }[];
}>();

const active = defineModel<string>();
</script>

<template>
    <TabsRoot v-model="active" :default-value="tabs[0]?.value">
        <TabsList class="no-scrollbar mb-5 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-sm text-muted hover:text-ink data-[state=active]:border-brand data-[state=active]:font-medium data-[state=active]:text-ink"
            >
                {{ tab.label }}
                <span v-if="tab.hasError" class="ms-1 inline-block size-1.5 rounded-full bg-danger align-middle" />
            </TabsTrigger>
        </TabsList>
        <!-- unmount-on-hide off: a field on a hidden tab keeps its value and can still show its error. -->
        <TabsContent v-for="tab in tabs" :key="tab.value" :value="tab.value" :unmount-on-hide="false" class="data-[state=inactive]:hidden">
            <slot :name="tab.value" />
        </TabsContent>
    </TabsRoot>
</template>
