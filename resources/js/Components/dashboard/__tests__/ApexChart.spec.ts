import { flushPromises, mount } from '@vue/test-utils';
import type { ApexOptions } from 'apexcharts';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

import { chartOptions, escapeHtml } from '@/lib/chartOptions';
import { useThemeProvider } from '@/lib/useTheme';

import ApexChart from '../ApexChart.vue';

/** What the chart hands to ApexCharts, recorded instead of drawn. */
const received: { options?: ApexOptions } = {};

vi.mock('vue3-apexcharts', () => ({
    __esModule: true,
    default: defineComponent({
        props: { options: { type: Object, default: () => ({}) }, series: { type: Array, default: () => [] }, type: String, height: Number },
        setup(props) {
            return () => {
                received.options = props.options as ApexOptions;
                return h('div');
            };
        },
    }),
}));

const evil = '<img src=x onerror=alert(1)>';
const escaped = '&lt;img src=x onerror=alert(1)&gt;';

type Formatter = (value: unknown, opts?: unknown) => string;

describe('ApexChart', () => {
    it('escapes HTML', () => {
        expect(escapeHtml(`a & "b" <c> 'd'`)).toBe('a &amp; &quot;b&quot; &lt;c&gt; &#39;d&#39;');
        expect(escapeHtml(12)).toBe('12');
    });

    it('never hands ApexCharts a label, category or series name it would write as HTML', async () => {
        // As in the admin: AdminLayout provides the theme.
        const Page = defineComponent({
            setup() {
                useThemeProvider();
                return () => h(ApexChart, { type: 'bar', series: [{ name: evil, data: [1] }], categories: [evil] });
            },
        });
        mount(Page);
        await flushPromises();

        const options = received.options!;
        expect((options.tooltip!.x!.formatter as Formatter)(evil)).toBe(escaped);
        expect((options.xaxis!.tooltip!.formatter as Formatter)(evil)).toBe(escaped);
        expect((options.tooltip!.y as { title: { formatter: Formatter } }).title.formatter(evil)).toBe(escaped);
        expect((options.legend!.formatter as Formatter)(evil)).toBe(escaped);
        // Axis labels are SVG text (set as textContent): escaping them would show "&lt;".
        expect(options.xaxis!.categories).toEqual([evil]);
    });

    it('escapes donut labels in the tooltip and legend', () => {
        const options = chartOptions({ type: 'donut', labels: [evil], colors: ['#000'] }, false);

        expect((options.tooltip!.y as { title: { formatter: Formatter } }).title.formatter(evil)).toBe(escaped);
        expect((options.legend!.formatter as Formatter)(evil)).toBe(escaped);
    });
});
