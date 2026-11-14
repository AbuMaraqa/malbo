import { ref } from 'vue';

import { forgetPixel } from './metaPixel';

/**
 * The marketing cookies banner, reopened from the footer's "Cookie settings", and what taking
 * consent back does in the browser. Only called from event handlers: it touches `document` and
 * `window`.
 */
export const consentBannerOpen = ref(false);

export function openConsentBanner(): void {
    consentBannerOpen.value = true;
}

const PIXEL_COOKIES = ['_fbp', '_fbc'];

/**
 * Expires the Pixel's cookies wherever its script may have set them: this host, and each parent
 * domain (it writes them on the top domain it can).
 */
export function forgetPixelCookies(doc: Document = document): void {
    const host = doc.location?.hostname ?? '';
    const parts = host.split('.');
    const domains = [''];

    if (host !== '') {
        domains.push(`; domain=${host}`);

        for (let index = 1; index < parts.length; index++) {
            domains.push(`; domain=.${parts.slice(index).join('.')}`);
        }
    }

    for (const name of PIXEL_COOKIES) {
        for (const domain of domains) {
            doc.cookie = `${name}=; Max-Age=0; path=/${domain}`;
        }
    }
}

/**
 * Consent taken back: the Pixel's cookies go and the page loads again in full, so the Pixel script
 * already running is gone too.
 */
export function withdrawMarketing(win: Window = window): void {
    forgetPixelCookies(win.document);
    forgetPixel();
    win.location.reload();
}
