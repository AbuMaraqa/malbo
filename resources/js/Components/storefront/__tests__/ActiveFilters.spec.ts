import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import ActiveFilters from '../ActiveFilters.vue';

const facets = {
    attributes: [{ id: 3, name: 'اللون', swatch: true, values: [{ id: 12, name: 'أسود', color: '#000000', count: 4, selected: true }] }],
    price: { min: 50, max: 2000 },
};

describe('ActiveFilters', () => {
    it('shows the price range as the cards show prices', () => {
        const wrapper = mount(ActiveFilters, { props: { facets, filters: { q: '', sort: 'newest', attr: { 3: [12] }, price_min: 100, price_max: 1250.5 } } });

        expect(wrapper.text()).toContain('100.00 ₪');
        expect(wrapper.text()).toContain('1,250.50 ₪');
    });

    it('removes a chosen value and clears everything', async () => {
        const wrapper = mount(ActiveFilters, { props: { facets, filters: { q: '', sort: 'newest', attr: { 3: [12] }, price_min: null, price_max: 300 } } });
        const buttons = wrapper.findAll('button');

        await buttons[0].trigger('click');
        await buttons[1].trigger('click');
        await buttons[buttons.length - 1].trigger('click');

        expect(wrapper.emitted('toggle')).toEqual([[3, 12]]);
        expect(wrapper.emitted('price')).toEqual([[null, null]]);
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });
});
