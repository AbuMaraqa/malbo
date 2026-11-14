import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { applyMarketing, forgetPixel, type MarketingProps } from '../metaPixel';

type Fbq = { (...args: unknown[]): void; queue: unknown[][] };

const fbq = () => (window as unknown as { fbq?: Fbq }).fbq;
const pixelScripts = () => document.querySelectorAll('script[src="https://connect.facebook.net/en_US/fbevents.js"]');

const granted: MarketingProps = {
    consent: 'granted',
    pixel_id: '1234567890',
    events: [
        { name: 'PageView', id: 'pv-1', data: {} },
        { name: 'ViewContent', id: 'vc-1', data: { content_ids: ['SHIRT-M'], value: 80, currency: 'ILS' } },
    ],
};

describe('metaPixel', () => {
    beforeEach(() => {
        document.head.innerHTML = '<script></script>';
    });

    afterEach(() => {
        forgetPixel();
        delete (window as unknown as { fbq?: Fbq }).fbq;
        delete (window as unknown as { _fbq?: Fbq })._fbq;
    });

    it('loads nothing before the visitor accepts', () => {
        applyMarketing({ consent: null, pixel_id: null, events: [] });
        applyMarketing({ consent: 'denied', pixel_id: null, events: [] });
        applyMarketing(null);
        // Even a page that wrongly carried an id and events stays silent without consent.
        applyMarketing({ ...granted, consent: 'denied' });

        expect(pixelScripts()).toHaveLength(0);
        expect(fbq()).toBeUndefined();
    });

    it('loads the Pixel once and fires each event with its server id', () => {
        applyMarketing(granted);
        applyMarketing(granted);

        expect(pixelScripts()).toHaveLength(1);
        // No automatic events: the Pixel would count a PageView of its own on every Inertia visit
        // (history changes) and on button clicks, beside the server-made ones.
        expect((fbq() as unknown as { disablePushState: boolean }).disablePushState).toBe(true);
        expect(fbq()!.queue.map((call) => Array.from(call))).toEqual([
            ['set', 'autoConfig', false, '1234567890'],
            ['init', '1234567890'],
            ['track', 'PageView', {}, { eventID: 'pv-1' }],
            ['track', 'ViewContent', { content_ids: ['SHIRT-M'], value: 80, currency: 'ILS' }, { eventID: 'vc-1' }],
        ]);
    });

    it('fires the events of the next page only', () => {
        applyMarketing(granted);
        applyMarketing({ ...granted, events: [granted.events[1], { name: 'AddToCart', id: 'atc-1', data: { value: 160 } }] });

        const tracked = fbq()!.queue.filter((call) => call[0] === 'track').map((call) => (call[3] as { eventID: string }).eventID);
        expect(tracked).toEqual(['pv-1', 'vc-1', 'atc-1']);
    });

    it('needs a Pixel id: the server-only setup loads no script', () => {
        applyMarketing({ consent: 'granted', pixel_id: null, events: [] });

        expect(pixelScripts()).toHaveLength(0);
    });
});
