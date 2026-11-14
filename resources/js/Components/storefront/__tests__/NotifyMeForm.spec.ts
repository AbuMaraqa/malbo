import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import { forms } from '@/test/setup';

import NotifyMeForm from '../NotifyMeForm.vue';

function mounted(productId = 7) {
    const wrapper = mount(NotifyMeForm, { props: { productId } });

    return { wrapper, form: forms[forms.length - 1] };
}

describe('NotifyMeForm', () => {
    it('needs an email or a phone before it sends', async () => {
        const { wrapper, form } = mounted();
        const submit = wrapper.find('button[type="submit"]');

        expect(submit.attributes('disabled')).toBeDefined();

        await wrapper.find('input[type="email"]').setValue('buyer@example.com');
        expect(submit.attributes('disabled')).toBeUndefined();

        await wrapper.find('input[type="email"]').setValue('  ');
        await wrapper.find('input[type="tel"]').setValue('0599123456');
        expect(submit.attributes('disabled')).toBeUndefined();
        expect(form.phone).toBe('0599123456');
    });

    it('sends the chosen variant with the contact, keeping the page where it is', async () => {
        const { wrapper, form } = mounted(7);
        await wrapper.find('input[type="email"]').setValue('buyer@example.com');
        await wrapper.find('form').trigger('submit');

        expect(form.product_id).toBe(7);
        expect(form.post).toHaveBeenCalledWith('http://localhost/ar/stock-alerts', expect.objectContaining({ preserveScroll: true, preserveState: true }));
    });

    it('carries a honeypot people never see', () => {
        const { wrapper, form } = mounted();

        const trap = wrapper.find('input[name="website"]');
        expect(trap.exists()).toBe(true);
        expect(trap.attributes('tabindex')).toBe('-1');
        expect(form.website).toBe('');
    });

    it('thanks the buyer once saved, and starts over for another variant', async () => {
        const { wrapper, form } = mounted(7);
        await wrapper.find('input[type="email"]').setValue('buyer@example.com');
        await wrapper.find('form').trigger('submit');

        const options = form.post.mock.calls[0][1] as { onSuccess: () => void };
        options.onSuccess();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="status"]').exists()).toBe(true);
        expect(wrapper.find('form').exists()).toBe(false);

        await wrapper.setProps({ productId: 8 });
        expect(form.product_id).toBe(8);
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('shows what the server refused', async () => {
        const { wrapper, form } = mounted();
        form.errors = { email: 'Enter a valid email.', product_id: 'This item can be ordered now.' };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Enter a valid email.');
        expect(wrapper.text()).toContain('This item can be ordered now.');
    });
});
