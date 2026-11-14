import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import BundleComponentPicker from '../BundleComponentPicker.vue';
import type { BundleComponentOption } from '../types';

const shirt: BundleComponentOption = {
    id: 5,
    name: 'Shirt',
    quantity: 1,
    image: '/shirt.jpg',
    choosable: true,
    fixed: null,
    available: true,
    attributes: [
        {
            id: 1,
            name: 'Size',
            swatch: false,
            size: true,
            values: [
                { id: 10, name: 'M', color: null },
                { id: 11, name: 'L', color: null },
            ],
        },
    ],
    variants: [
        { id: 100, values: { 1: 10 }, in_stock: true, image: '/shirt-m.jpg' },
        { id: 101, values: { 1: 11 }, in_stock: false, image: null },
    ],
};

function mounted(component: BundleComponentOption, modelValue?: number) {
    const emitted: (number | undefined)[] = [];
    const wrapper = mount(BundleComponentPicker, {
        props: { component, modelValue, 'onUpdate:modelValue': (value: number | undefined) => emitted.push(value) },
    });

    return { wrapper, emitted };
}

describe('BundleComponentPicker', () => {
    it('turns the chosen size into the variant it makes', async () => {
        const { wrapper, emitted } = mounted(shirt);

        await wrapper.find('input[value="10"]').setValue(true);

        expect(emitted.at(-1)).toBe(100);
        expect(wrapper.find('img').attributes('src')).toBe('/shirt-m.jpg');
        expect(wrapper.text()).not.toContain('Currently unavailable');
    });

    it('says when the chosen size is out of stock', async () => {
        const { wrapper, emitted } = mounted(shirt);

        await wrapper.find('input[value="11"]').setValue(true);

        expect(emitted.at(-1)).toBe(101);
        expect(wrapper.find('[role="alert"]').text()).toBe('Currently unavailable');
    });

    it('starts from the variant already chosen', () => {
        const { wrapper } = mounted(shirt, 101);

        expect((wrapper.find('input[value="11"]').element as HTMLInputElement).checked).toBe(true);
    });

    it('keeps the radio groups of two pieces apart', () => {
        const { wrapper } = mounted(shirt);

        expect(wrapper.find('input[value="10"]').attributes('name')).toBe('component-5-attribute-1');
    });

    it('shows a piece without a choice as it is', () => {
        const { wrapper } = mounted({ ...shirt, id: 6, name: 'Belt', quantity: 2, choosable: false, fixed: 'Black', attributes: [], variants: [] });

        expect(wrapper.findAll('input')).toHaveLength(0);
        expect(wrapper.text()).toContain('Belt');
        expect(wrapper.text()).toContain('×2');
        expect(wrapper.text()).toContain('Black');
    });
});
