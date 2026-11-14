<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { controlClasses } from '@/Components/form/controlClasses';
import { buttonClasses } from '@/lib/buttonClasses';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { Calendar, Download } from '@/lib/icons';

import { applyFilters, currentFilters } from './reportQuery';
import type { ReportFilterProps, ReportOption } from './types';

/**
 * The filters every report shares: the period (presets or a custom range, in the reports'
 * timezone), and, where the report offers them, the channel, branch, category, grouping and
 * day/month interval. Each change reloads the report with the URL's other filters kept.
 */
const props = withDefaults(
    defineProps<{
        filters: ReportFilterProps;
        channels?: boolean;
        branches?: ReportOption[];
        categories?: ReportOption[];
        groupings?: ReportOption[];
        grouping?: string;
        intervals?: boolean;
        exportUrl?: string;
    }>(),
    { channels: false, branches: () => [], categories: () => [], groupings: () => [], grouping: undefined, intervals: false, exportUrl: undefined },
);

const presets = computed(() => [
    { value: 'today', label: t('Today') },
    { value: '7d', label: t('Last 7 days') },
    { value: 'this_month', label: t('This month') },
    { value: 'last_month', label: t('Last month') },
    { value: 'custom', label: t('Custom range') },
]);

const customOpen = ref(props.filters.preset === 'custom');
const from = ref(props.filters.date_from);
const to = ref(props.filters.date_to);
const rangeValid = computed(() => !!from.value && !!to.value && from.value <= to.value);

// The table's own filter menu can change the period too: follow what the server resolved.
watch(
    () => [props.filters.date_from, props.filters.date_to, props.filters.preset],
    () => {
        from.value = props.filters.date_from;
        to.value = props.filters.date_to;
        customOpen.value = props.filters.preset === 'custom';
    },
);

function visit(changes: Record<string, string | number | null>): void {
    router.get(window.location.pathname, { filter: applyFilters(currentFilters(window.location.search), changes) }, { preserveScroll: true });
}

function choosePreset(preset: string): void {
    if (preset === 'custom') {
        customOpen.value = true;
        return;
    }

    customOpen.value = false;
    visit({ preset });
}

function applyRange(): void {
    if (rangeValid.value) {
        visit({ preset: 'custom', date_from: from.value, date_to: to.value });
    }
}

function isActive(preset: string): boolean {
    return customOpen.value ? preset === 'custom' : props.filters.preset === preset;
}

const select = cn(controlClasses, 'h-9 w-auto pe-8');
</script>

<template>
    <div class="mb-6 flex flex-col gap-3 rounded-xl border border-line bg-card p-4 shadow-card">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 dark:bg-white/5" role="group" :aria-label="t('Period')">
                <button
                    v-for="preset in presets"
                    :key="preset.value"
                    type="button"
                    :data-preset="preset.value"
                    :aria-pressed="isActive(preset.value) ? 'true' : 'false'"
                    class="rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors"
                    :class="isActive(preset.value) ? 'bg-card text-ink shadow-theme-xs' : 'text-muted hover:text-ink'"
                    @click="choosePreset(preset.value)"
                >
                    {{ preset.label }}
                </button>
            </div>

            <select v-if="channels" name="channel" :aria-label="t('Channel')" :class="select" :value="filters.channel ?? ''" @change="visit({ channel: ($event.target as HTMLSelectElement).value })">
                <option value="">{{ t('All channels') }}</option>
                <option value="b2c">{{ t('Store (B2C)') }}</option>
                <option value="b2b">{{ t('Wholesale (B2B)') }}</option>
            </select>

            <select v-if="branches.length" name="branch" :aria-label="t('Branch')" :class="select" :value="filters.branch ?? ''" @change="visit({ branch: ($event.target as HTMLSelectElement).value })">
                <option value="">{{ t('All branches') }}</option>
                <option v-for="option in branches" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <select v-if="categories.length" name="category" :aria-label="t('Category')" :class="select" :value="filters.category ?? ''" @change="visit({ category: ($event.target as HTMLSelectElement).value })">
                <option value="">{{ t('All categories') }}</option>
                <option v-for="option in categories" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <select v-if="groupings.length" name="by" :aria-label="t('Group by')" :class="select" :value="grouping ?? groupings[0]?.value" @change="visit({ by: ($event.target as HTMLSelectElement).value })">
                <option v-for="option in groupings" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <select v-if="intervals" name="interval" :aria-label="t('Interval')" :class="select" :value="filters.interval" @change="visit({ interval: ($event.target as HTMLSelectElement).value })">
                <option value="day">{{ t('Daily') }}</option>
                <option value="month">{{ t('Monthly') }}</option>
            </select>

            <a v-if="exportUrl" :href="exportUrl" :class="buttonClasses({ variant: 'secondary', size: 'sm', class: 'ms-auto' })" download>
                <Download />{{ t('Export CSV') }}
            </a>
        </div>

        <div v-if="customOpen" class="flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1 text-xs text-muted">
                {{ t('From') }}
                <input v-model="from" name="date_from" type="date" :class="cn(controlClasses, 'h-9 w-auto')" />
            </label>
            <label class="flex flex-col gap-1 text-xs text-muted">
                {{ t('To') }}
                <input v-model="to" name="date_to" type="date" :class="cn(controlClasses, 'h-9 w-auto')" />
            </label>
            <button type="button" data-apply-range :disabled="!rangeValid" :class="buttonClasses({ variant: 'primary', size: 'sm' })" @click="applyRange">{{ t('Apply') }}</button>
        </div>

        <p class="flex items-center gap-1.5 text-xs text-muted">
            <Calendar class="size-3.5" />
            <span>{{ t('From :from to :to', { from: filters.date_from, to: filters.date_to }) }}</span>
            <span aria-hidden="true">·</span>
            <span dir="ltr">{{ filters.timezone }}</span>
        </p>
    </div>
</template>
