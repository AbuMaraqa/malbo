import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';

import { router } from '@/test/setup';

import { applyFilters, currentFilters } from '../reportQuery';
import ReportFilterBar from '../ReportFilterBar.vue';
import type { ReportFilterProps } from '../types';

const filters: ReportFilterProps = {
    preset: 'custom',
    date_from: '2026-11-01',
    date_to: '2026-11-05',
    channel: 'b2b',
    branch: null,
    category: null,
    interval: 'day',
    by: null,
    timezone: 'Asia/Jerusalem',
};

describe('reportQuery', () => {
    it('reads the filter keys of a query string', () => {
        expect(currentFilters('?filter[channel]=b2b&filter[date_from]=2026-11-01&page=2&sort=-net')).toEqual({ channel: 'b2b', date_from: '2026-11-01' });
    });

    it('drops explicit dates when a preset is chosen, and empty values', () => {
        expect(applyFilters({ date_from: '2026-11-01', date_to: '2026-11-05', channel: 'b2b', search: 'x' }, { preset: 'today' })).toEqual({ channel: 'b2b', search: 'x', preset: 'today' });
        expect(applyFilters({ channel: 'b2b', category: '4' }, { channel: null, category: '' })).toEqual({});
    });
});

describe('ReportFilterBar', () => {
    beforeEach(() => {
        router.get.mockClear();
        window.history.replaceState({}, '', '/ar/admin/reports/sales?filter[date_from]=2026-11-01&filter[date_to]=2026-11-05&filter[channel]=b2b');
    });

    const mountBar = (extra: Record<string, unknown> = {}) => mount(ReportFilterBar, { props: { filters, channels: true, ...extra }, attachTo: document.body });

    it('marks the active preset', () => {
        const wrapper = mountBar({ filters: { ...filters, preset: 'this_month' } });

        expect(wrapper.find('[data-preset="this_month"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('[data-preset="today"]').attributes('aria-pressed')).toBe('false');
        wrapper.unmount();
    });

    it('applies a preset and drops explicit dates', async () => {
        const wrapper = mountBar();

        await wrapper.find('[data-preset="today"]').trigger('click');

        expect(router.get).toHaveBeenCalledWith('/ar/admin/reports/sales', { filter: { channel: 'b2b', preset: 'today' } }, expect.objectContaining({ preserveScroll: true }));
        wrapper.unmount();
    });

    it('applies a custom range', async () => {
        const wrapper = mountBar();

        await wrapper.find('[data-preset="custom"]').trigger('click');
        await wrapper.find('input[name="date_from"]').setValue('2026-10-01');
        await wrapper.find('input[name="date_to"]').setValue('2026-10-15');
        await wrapper.find('[data-apply-range]').trigger('click');

        expect(router.get).toHaveBeenCalledWith(
            '/ar/admin/reports/sales',
            { filter: { channel: 'b2b', preset: 'custom', date_from: '2026-10-01', date_to: '2026-10-15' } },
            expect.anything(),
        );
        wrapper.unmount();
    });

    it('refuses a reversed range', async () => {
        const wrapper = mountBar();

        await wrapper.find('[data-preset="custom"]').trigger('click');
        await wrapper.find('input[name="date_from"]').setValue('2026-10-20');
        await wrapper.find('input[name="date_to"]').setValue('2026-10-15');

        expect(wrapper.find('[data-apply-range]').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });

    it('keeps other filters when the channel changes', async () => {
        const wrapper = mountBar();

        await wrapper.find('select[name="channel"]').setValue('');

        expect(router.get).toHaveBeenCalledWith('/ar/admin/reports/sales', { filter: { date_from: '2026-11-01', date_to: '2026-11-05' } }, expect.anything());
        wrapper.unmount();
    });

    it('shows only the selectors a report offers', () => {
        const wrapper = mountBar({ channels: false, categories: [{ value: 3, label: 'قمصان' }] });

        expect(wrapper.find('select[name="channel"]').exists()).toBe(false);
        expect(wrapper.find('select[name="category"]').exists()).toBe(true);
        expect(wrapper.find('select[name="branch"]').exists()).toBe(false);
        wrapper.unmount();
    });
});
