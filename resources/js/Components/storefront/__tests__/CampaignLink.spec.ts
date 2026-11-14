import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import CampaignLink from '../campaign/CampaignLink.vue';

function anchor(href: string) {
    return mount(CampaignLink, { props: { href }, slots: { default: 'Go' } }).find('a');
}

describe('CampaignLink', () => {
    it('opens a page of this store in place', () => {
        const link = anchor('/ar/shop');

        expect(link.attributes('href')).toBe('/ar/shop');
        expect(link.attributes('target')).toBeUndefined();
    });

    it('treats anything else as another site, in a new tab', () => {
        for (const href of ['https://example.com', '//evil.example', '/\\evil.example', 'evil.example']) {
            const link = anchor(href);

            expect(link.attributes('target')).toBe('_blank');
            expect(link.attributes('rel')).toBe('noopener');
        }
    });
});
