import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';

import { router } from '@/test/setup';

import CompleteTheLook from '../CompleteTheLook.vue';
import type { LookItem } from '../types';

const price = { amount: 150, label: '150.00 ₪', was: null, percent_off: null, discounted: false };

const shirt: LookItem = {
    id: 1,
    name: 'Shirt',
    url: '/ar/product/shirt',
    image: null,
    price,
    in_stock: true,
    variants: [
        { id: 11, label: 'M', in_stock: true },
        { id: 12, label: 'L', in_stock: false },
    ],
};

const belt: LookItem = { id: 2, name: 'Belt', url: '/ar/product/belt', image: null, price, in_stock: true, variants: [] };

describe('CompleteTheLook', () => {
    beforeEach(() => router.post.mockClear());

    it('adds the size the buyer picks, and nothing before one is picked', async () => {
        const wrapper = mount(CompleteTheLook, { props: { items: [shirt] } });
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeDefined();

        await wrapper.find('select').setValue('11');
        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');
        expect(router.post).toHaveBeenCalledWith('http://localhost/ar/cart', { product_id: 11, quantity: 1 }, expect.objectContaining({ preserveScroll: true }));
    });

    it('offers sizes out of stock as disabled', () => {
        const wrapper = mount(CompleteTheLook, { props: { items: [shirt] } });
        const options = wrapper.findAll('option');

        expect(options.map((option) => option.attributes('disabled') !== undefined)).toEqual([false, false, true]);
    });

    it('adds a piece without sizes as it is', async () => {
        const wrapper = mount(CompleteTheLook, { props: { items: [belt] } });

        expect(wrapper.find('select').exists()).toBe(false);
        await wrapper.find('button').trigger('click');

        expect(router.post).toHaveBeenCalledWith('http://localhost/ar/cart', { product_id: 2, quantity: 1 }, expect.anything());
    });

    it('cannot add a piece that is out of stock', () => {
        const wrapper = mount(CompleteTheLook, { props: { items: [{ ...belt, in_stock: false }] } });

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Currently unavailable');
    });
});
