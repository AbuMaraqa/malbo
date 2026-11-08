<script setup lang="ts">
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

export interface WorkingHour {
    day: number;
    is_closed: boolean;
    start_time: string | null;
    end_time: string | null;
}

const props = defineProps<{ hours: WorkingHour[]; days: Option[]; errors: Record<string, string | undefined> }>();

const dayLabel = (day: number): string => String(props.days.find((option) => option.value === day)?.label ?? day);
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[32rem] text-sm">
            <thead>
                <tr class="border-b border-line text-start text-muted">
                    <th class="py-2 pe-3 text-start font-medium">{{ t('Day') }}</th>
                    <th class="py-2 pe-3 text-start font-medium">{{ t('Closed?') }}</th>
                    <th class="py-2 pe-3 text-start font-medium">{{ t('Start') }}</th>
                    <th class="py-2 text-start font-medium">{{ t('End') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(hour, index) in hours" :key="hour.day" class="border-b border-line last:border-0">
                    <td class="py-2 pe-3 font-medium">{{ dayLabel(hour.day) }}</td>
                    <td class="py-2 pe-3">
                        <Toggle v-model="hour.is_closed" :aria-label="t('Closed?')" />
                    </td>
                    <td class="py-2 pe-3">
                        <TextInput v-model="hour.start_time" type="time" dir="ltr" :disabled="hour.is_closed" :error="errors[`working_hours.${index}.start_time`]" />
                    </td>
                    <td class="py-2">
                        <TextInput v-model="hour.end_time" type="time" dir="ltr" :disabled="hour.is_closed" :error="errors[`working_hours.${index}.end_time`]" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
