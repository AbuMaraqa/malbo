import { afterEach, describe, expect, it, vi } from 'vitest';

import { consentBannerOpen, forgetPixelCookies, openConsentBanner, withdrawMarketing } from '../consent';

describe('consent', () => {
    afterEach(() => {
        consentBannerOpen.value = false;
    });

    it('reopens the banner', () => {
        openConsentBanner();

        expect(consentBannerOpen.value).toBe(true);
    });

    it('forgets the Pixel cookies and nothing else', () => {
        document.cookie = '_fbp=fb.1.1.123; path=/';
        document.cookie = '_fbc=fb.1.1.abc; path=/';
        document.cookie = 'other=kept; path=/';

        forgetPixelCookies(document);

        expect(document.cookie).not.toContain('_fbp');
        expect(document.cookie).not.toContain('_fbc');
        expect(document.cookie).toContain('other=kept');
    });

    it('expires them on the site and every parent domain', () => {
        const written: string[] = [];
        const doc = { location: { hostname: 'shop.malbo.example' } } as unknown as Document;
        Object.defineProperty(doc, 'cookie', { set: (value: string) => written.push(value), get: () => '' });

        forgetPixelCookies(doc);

        for (const domain of ['', '; domain=shop.malbo.example', '; domain=.malbo.example', '; domain=.example']) {
            expect(written).toContain(`_fbp=; Max-Age=0; path=/${domain}`);
        }
    });

    it('reloads the page in full once consent is taken back', () => {
        const reload = vi.fn();
        const win = { document, location: { reload } } as unknown as Window;
        document.cookie = '_fbp=fb.1.1.123; path=/';

        withdrawMarketing(win);

        expect(document.cookie).not.toContain('_fbp');
        expect(reload).toHaveBeenCalledOnce();
    });
});
