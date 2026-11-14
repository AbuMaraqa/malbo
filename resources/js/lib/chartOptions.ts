import type { ApexOptions } from 'apexcharts';

/**
 * Text as HTML that shows it literally: `<img onerror>` becomes `&lt;img onerror&gt;`.
 */
export function escapeHtml(value: unknown): string {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export interface ChartLook {
    type: 'bar' | 'area' | 'line' | 'donut';
    categories?: string[];
    labels?: string[];
    colors: string[];
}

/**
 * The options of every chart in the admin. Labels, categories and series names are data (a shop's
 * or a product's name), and ApexCharts writes some of them as HTML (the tooltip title, the x-axis
 * tooltip, a series name in the tooltip, a legend item a formatter changed): each of those goes
 * through escapeHtml here, so no chart renders markup from data. Axis labels are SVG text (set as
 * textContent) and stay as they are.
 */
export function chartOptions(look: ChartLook, dark: boolean): ApexOptions {
    const text = dark ? '#9ba3b8' : '#6c7590';
    const escape = (value: unknown) => escapeHtml(value);

    return {
        colors: look.colors,
        labels: look.labels,
        chart: {
            type: look.type,
            fontFamily: 'var(--font-sans)',
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
        },
        theme: { mode: dark ? 'dark' : 'light' },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: look.type === 'bar' || look.type === 'donut' ? 0 : 2 },
        fill: look.type === 'area' ? { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0 } } : {},
        plotOptions: { bar: { columnWidth: '38%', borderRadius: 6, borderRadiusApplication: 'end' } },
        grid: { borderColor: dark ? '#232a3d' : '#eef0f5', strokeDashArray: 4, yaxis: { lines: { show: true } } },
        xaxis: {
            categories: look.categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: text } },
            tooltip: { formatter: escape },
        },
        yaxis: { labels: { style: { colors: [text] } } },
        tooltip: {
            x: { formatter: escape },
            y: { title: { formatter: escape } },
        },
        legend: { position: 'bottom', labels: { colors: text }, formatter: escape },
    };
}
