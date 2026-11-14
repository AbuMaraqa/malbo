import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';

import FilterDrawer from '../FilterDrawer.vue';

/** Reka's focus moves wait a frame or two. */
async function settle(): Promise<void> {
    for (let i = 0; i < 4; i++) {
        await new Promise((resolve) => setTimeout(resolve, 20));
        await flushPromises();
        await nextTick();
    }
}

describe('FilterDrawer', () => {
    it('opens from its button, traps focus inside, closes on Escape and gives focus back', async () => {
        const wrapper = mount(FilterDrawer, {
            props: { totalLabel: '12 منتج', count: 2 },
            slots: { default: '<button type="button" class="inside">أسود</button>' },
            attachTo: document.body,
        });
        const opener = wrapper.find('button');

        expect(opener.attributes('aria-expanded')).toBe('false');
        opener.element.focus();
        await opener.trigger('click');
        await settle();

        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog).not.toBeNull();
        expect(opener.attributes('aria-expanded')).toBe('true');
        expect(opener.attributes('aria-controls')).toBe(dialog.id);
        expect(dialog.contains(document.activeElement)).toBe(true);

        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await settle();

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
        expect(document.activeElement).toBe(opener.element);
        wrapper.unmount();
    });

    it('closes on the results button', async () => {
        const wrapper = mount(FilterDrawer, { props: { totalLabel: '12 منتج', count: 0 }, attachTo: document.body });
        await wrapper.find('button').trigger('click');
        await settle();

        const show = [...document.body.querySelectorAll('[role="dialog"] button')].find((button) => button.textContent?.includes('12 منتج')) as HTMLButtonElement;
        show.click();
        await settle();

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
        wrapper.unmount();
    });
});
