import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { consentBannerOpen, openConsentBanner, withdrawMarketing } from '@/lib/consent';
import { router } from '@/test/setup';

vi.mock('@/lib/consent', async (original) => ({ ...(await original<typeof import('@/lib/consent')>()), withdrawMarketing: vi.fn() }));

import ConsentBanner from '../ConsentBanner.vue';

describe('ConsentBanner', () => {
    beforeEach(() => router.post.mockClear());

    it('asks a visitor who has not answered', () => {
        const wrapper = mount(ConsentBanner, { props: { marketing: { consent: null, pixel_id: null, events: [] } } });

        expect(wrapper.find('[role="region"]').exists()).toBe(true);
        expect(wrapper.findAll('button')).toHaveLength(2);
    });

    it('stays hidden once answered, or when the store tracks nothing', () => {
        for (const marketing of [{ consent: 'granted' as const, pixel_id: '1', events: [] }, { consent: 'denied' as const, pixel_id: null, events: [] }, null]) {
            expect(mount(ConsentBanner, { props: { marketing } }).find('[role="region"]').exists()).toBe(false);
        }
    });

    it('sends the answer and reloads only the marketing prop', async () => {
        const wrapper = mount(ConsentBanner, { props: { marketing: { consent: null, pixel_id: null, events: [] } } });

        await wrapper.find('[data-choice="granted"]').trigger('click');
        expect(router.post).toHaveBeenCalledWith('http://localhost/ar/consent', { choice: 'granted' }, expect.objectContaining({ only: ['marketing'], preserveScroll: true, preserveState: true }));

        // The buttons wait for the answer to be saved; a new visit gets a new banner.
        expect(wrapper.find('[data-choice="denied"]').attributes('disabled')).toBeDefined();
        const again = mount(ConsentBanner, { props: { marketing: { consent: null, pixel_id: null, events: [] } } });
        await again.find('[data-choice="denied"]').trigger('click');
        expect(router.post).toHaveBeenLastCalledWith('http://localhost/ar/consent', { choice: 'denied' }, expect.objectContaining({ only: ['marketing'] }));
    });

    it('opens again from the footer, and taking consent back reloads the page without the Pixel', async () => {
        const wrapper = mount(ConsentBanner, { props: { marketing: { consent: 'granted' as const, pixel_id: '1', events: [] } } });
        expect(wrapper.find('[role="region"]').exists()).toBe(false);

        openConsentBanner();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="region"]').exists()).toBe(true);

        await wrapper.find('[data-choice="denied"]').trigger('click');
        const options = router.post.mock.calls.at(-1)![2] as { onSuccess: () => void; only?: string[] };
        options.onSuccess();

        expect(withdrawMarketing).toHaveBeenCalledOnce();
        consentBannerOpen.value = false;
    });
});
