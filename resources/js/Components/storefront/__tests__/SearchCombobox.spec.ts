import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { router } from '@/test/setup';

import SearchCombobox from '../SearchCombobox.vue';

const answer = {
    products: [
        { name: 'قميص أسود', image: null, price: '150.00 ₪', was: '200.00 ₪', url: '/ar/product/black' },
        { name: 'قميص أبيض', image: null, price: '120.00 ₪', was: null, url: '/ar/product/white' },
    ],
    categories: [{ name: 'قمصان', url: '/ar/category/shirts' }],
};

/** A fetch the test answers when it wants; it records the signal it was given. */
function deferredFetch() {
    const calls: { url: string; signal: AbortSignal; resolve: () => void }[] = [];
    const fetch = vi.fn((url: string, init: { signal: AbortSignal }) => new Promise((resolve) => {
        calls.push({ url, signal: init.signal, resolve: () => resolve({ ok: true, json: async () => answer }) });
    }));

    return { fetch, calls };
}

describe('SearchCombobox', () => {
    let fake: ReturnType<typeof deferredFetch>;

    beforeEach(() => {
        vi.useFakeTimers();
        fake = deferredFetch();
        vi.stubGlobal('fetch', fake.fetch);
        router.get.mockClear();
        router.visit.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    async function typed(text: string) {
        const wrapper = mount(SearchCombobox, { attachTo: document.body });
        const input = wrapper.find('input');
        await input.trigger('focus');
        await input.setValue(text);

        return { wrapper, input };
    }

    async function opened() {
        const { wrapper, input } = await typed('قم');
        vi.advanceTimersByTime(250);
        fake.calls[0].resolve();
        await flushPromises();

        return { wrapper, input };
    }

    it('does not reopen after Enter while the suggestions were still waiting', async () => {
        const { wrapper, input } = await typed('قم');
        await input.trigger('keydown', { key: 'Enter' });
        await wrapper.find('form').trigger('submit');
        vi.advanceTimersByTime(500);
        await flushPromises();

        expect(fake.fetch).not.toHaveBeenCalled();
        expect(router.get).toHaveBeenCalledWith('http://localhost/ar/shop', { q: 'قم' });
        expect(input.attributes('aria-expanded')).toBe('false');
        wrapper.unmount();
    });

    it('drops the answer of a request Enter left behind', async () => {
        const { wrapper, input } = await typed('قم');
        vi.advanceTimersByTime(250);
        expect(fake.fetch).toHaveBeenCalledTimes(1);

        await wrapper.find('form').trigger('submit');
        expect(fake.calls[0].signal.aborted).toBe(true);
        fake.calls[0].resolve();
        await flushPromises();

        expect(input.attributes('aria-expanded')).toBe('false');
        wrapper.unmount();
    });

    it('stays closed when the answer arrives after the box lost focus', async () => {
        const { wrapper, input } = await typed('قم');
        vi.advanceTimersByTime(250);
        await input.trigger('blur');
        fake.calls[0].resolve();
        await flushPromises();

        expect(input.attributes('aria-expanded')).toBe('false');
        wrapper.unmount();
    });

    it('groups the options, moves the active one on hover and opens it', async () => {
        const { wrapper, input } = await opened();
        expect(input.attributes('aria-expanded')).toBe('true');

        const groups = wrapper.findAll('[role="group"]');
        expect(groups).toHaveLength(2);
        for (const group of groups) {
            expect(wrapper.find(`#${group.attributes('aria-labelledby')}`).exists()).toBe(true);
        }

        const options = wrapper.findAll('[role="option"]');
        await options[1].trigger('mouseenter');
        expect(input.attributes('aria-activedescendant')).toBe(options[1].attributes('id'));

        await input.trigger('keydown', { key: 'Enter' });
        await wrapper.find('form').trigger('submit');
        expect(router.visit).toHaveBeenCalledWith('/ar/product/white');
        wrapper.unmount();
    });

    it('keeps the list open while its scrollbar is dragged and reads the old price as such', async () => {
        const { wrapper } = await opened();
        const list = wrapper.find('[role="listbox"]');
        const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true });

        list.element.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(wrapper.find('s').text()).toContain('Was');
        wrapper.unmount();
    });
});
