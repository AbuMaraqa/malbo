import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PriceRangeFilter from '../PriceRangeFilter.vue';

describe('PriceRangeFilter', () => {
    const mountFilter = (min: number | null = null, max: number | null = null) =>
        mount(PriceRangeFilter, { props: { min, max, bounds: { min: 50, max: 900 } }, attachTo: document.body });

    it('applies the typed range', async () => {
        const wrapper = mountFilter();
        const [from, to] = wrapper.findAll('input');

        await from.setValue('100');
        await to.setValue('250.5');
        await wrapper.find('button[type="submit"]').trigger('click');

        expect(wrapper.emitted('apply')).toEqual([[100, 250.5]]);
        wrapper.unmount();
    });

    it('applies emptied bounds as none', async () => {
        const wrapper = mountFilter(100, 200);
        const [from, to] = wrapper.findAll('input');

        await from.setValue('');
        await to.setValue('');
        await wrapper.find('button[type="submit"]').trigger('click');

        expect(wrapper.emitted('apply')).toEqual([[null, null]]);
        wrapper.unmount();
    });
});
