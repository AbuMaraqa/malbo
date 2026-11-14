import { onMounted, watch } from 'vue';

import { applyMarketing, type MarketingProps } from './metaPixel';

/**
 * The storefront layout's Pixel: once mounted (never during SSR), and again whenever a visit brings
 * a new `marketing` prop (another page, or the consent just given).
 */
export function useMetaPixel(marketing: () => MarketingProps | null | undefined): void {
    onMounted(() => {
        applyMarketing(marketing());
        watch(marketing, (value) => applyMarketing(value));
    });
}
