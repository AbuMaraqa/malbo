<script setup lang="ts">
import { t } from '@/lib/i18n';
import type { TableFilter } from '@/types/table';

const props = defineProps<{
    filter: TableFilter;
    values: Record<string, string>;
}>();

const emit = defineEmits<{ change: [key: string, value: string] }>();

const control = 'h-9 w-full rounded-control border border-line bg-card px-2 text-sm outline-none focus:border-brand';

function value(key: string): string {
    return props.values[key] ?? '';
}

function update(key: string, event: Event): void {
    emit('change', key, (event.target as HTMLInputElement | HTMLSelectElement).value);
}
</script>

<template>
    <fieldset class="min-w-0">
        <legend class="mb-1 text-sm font-medium">{{ filter.label }}</legend>

        <select v-if="!filter.type || filter.type === 'select'" :class="control" :value="value(filter.key)" :aria-label="filter.label" @change="update(filter.key, $event)">
            <option value="">{{ t('All') }}</option>
            <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <select v-else-if="filter.type === 'boolean'" :class="control" :value="value(filter.key)" :aria-label="filter.label" @change="update(filter.key, $event)">
            <option value="">{{ t('All') }}</option>
            <option value="1">{{ filter.trueLabel ?? t('Yes') }}</option>
            <option value="0">{{ filter.falseLabel ?? t('No') }}</option>
        </select>

        <input v-else-if="filter.type === 'text'" type="text" :class="control" :value="value(filter.key)" :placeholder="filter.placeholder" :aria-label="filter.label" @change="update(filter.key, $event)" />

        <div v-else class="grid grid-cols-2 gap-2">
            <input
                :type="filter.type === 'date-range' ? 'date' : 'number'"
                :class="control"
                :value="value(`${filter.key}_from`)"
                :aria-label="`${filter.label}: ${t('From')}`"
                :placeholder="t('From')"
                @change="update(`${filter.key}_from`, $event)"
            />
            <input
                :type="filter.type === 'date-range' ? 'date' : 'number'"
                :class="control"
                :value="value(`${filter.key}_to`)"
                :aria-label="`${filter.label}: ${t('To')}`"
                :placeholder="t('To')"
                @change="update(`${filter.key}_to`, $event)"
            />
        </div>
    </fieldset>
</template>
