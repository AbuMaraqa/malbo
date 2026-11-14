<script setup lang="ts">
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

defineProps<{
    tabs: { value: string; label: string; hasError?: boolean }[];
}>();

const active = defineModel<string>();
</script>

<template>
    <TabsRoot v-model="active" :default-value="tabs[0]?.value">
        <TabsList class="no-scrollbar mb-5 flex gap-6 overflow-x-auto overflow-y-hidden border-b border-line">
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="-mb-px border-b-2 border-transparent py-3 text-sm font-medium whitespace-nowrap text-gray-500 transition-colors hover:text-brand-600 data-[state=active]:border-brand-500 data-[state=active]:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 dark:data-[state=active]:text-brand-400"
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
