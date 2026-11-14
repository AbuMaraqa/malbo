import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import { forms } from '@/test/setup';

import ReviewForm from '../ReviewForm.vue';

const SUBMIT = 'http://localhost/ar/product/shirt/reviews';

function mounted(review: { rating: number; title: string | null; body: string } | null = null) {
    const wrapper = mount(ReviewForm, { props: { submitUrl: SUBMIT, review } });

    return { wrapper, form: forms[forms.length - 1] };
}

function file(name: string, type: string, size = 1000): File {
    const value = new File(['x'], name, { type });
    Object.defineProperty(value, 'size', { value: size });

    return value;
}

async function choosePhotos(wrapper: ReturnType<typeof mount>, files: File[]) {
    const input = wrapper.find('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: files, configurable: true });
    await input.trigger('change');
}

describe('ReviewForm', () => {
    it('asks for stars and a text before it sends', async () => {
        const { wrapper, form } = mounted();

        await wrapper.find('form').trigger('submit');
        expect(form.post).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Choose a rating');

        await wrapper.find('input[name="rating"][value="4"]').setValue(true);
        await wrapper.find('textarea').setValue('قماش ممتاز');
        await wrapper.find('form').trigger('submit');

        expect(form.rating).toBe(4);
        expect(form.post).toHaveBeenCalledWith(SUBMIT, expect.objectContaining({ forceFormData: true, preserveScroll: true }));
    });

    it('keeps at most four photos of the allowed kinds and sizes', async () => {
        const { wrapper, form } = mounted();

        await choosePhotos(wrapper, [
            file('a.jpg', 'image/jpeg'),
            file('b.png', 'image/png'),
            file('c.gif', 'image/gif'),
            file('d.webp', 'image/webp', 6 * 1024 * 1024),
            file('e.webp', 'image/webp'),
            file('f.jpg', 'image/jpeg'),
            file('g.jpg', 'image/jpeg'),
        ]);

        expect((form.photos as File[]).map((photo) => photo.name)).toEqual(['a.jpg', 'b.png', 'e.webp', 'f.jpg']);
        expect(wrapper.text()).toContain('Some photos were left out');
    });

    it('starts from the buyer’s own review, which a new post replaces', () => {
        const { wrapper, form } = mounted({ rating: 3, title: 'جيد', body: 'رأيي السابق' });

        expect(form.rating).toBe(3);
        expect(form.body).toBe('رأيي السابق');
        expect(wrapper.text()).toContain('Sending it again replaces your review');
    });

    it('thanks the buyer once sent', async () => {
        const { wrapper, form } = mounted();
        await wrapper.find('input[name="rating"][value="5"]').setValue(true);
        await wrapper.find('textarea').setValue('رائع جدًا');
        await wrapper.find('form').trigger('submit');

        (form.post.mock.calls[0][1] as { onSuccess: () => void }).onSuccess();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="status"]').exists()).toBe(true);
    });
});
