import { describe, expect, it } from 'vitest';

import { brandIconFor } from '../brandIcons';

describe('brandIconFor', () => {
    it('finds the illustration from Arabic and English names', () => {
        expect(brandIconFor('الأحذية')).toBe('/brand/icons/shoes.svg');
        expect(brandIconFor('حذاء رياضي أبيض')).toBe('/brand/icons/shoes.svg');
        expect(brandIconFor('جاكيتات')).toBe('/brand/icons/jackets.svg');
        expect(brandIconFor('فوتر شتوي')).toBe('/brand/icons/hoodies.svg');
        expect(brandIconFor('بناطيل')).toBe('/brand/icons/pants.svg');
        expect(brandIconFor('بلايز')).toBe('/brand/icons/tees.svg');
        expect(brandIconFor('الإكسسوارات')).toBe('/brand/icons/accessories.svg');
        expect(brandIconFor('Running Sneakers')).toBe('/brand/icons/shoes.svg');
        expect(brandIconFor('Gift cards')).toBe('/brand/icons/gifts.svg');
    });

    it('takes the first kind in its order', () => {
        expect(brandIconFor('Denim jeans jacket')).toBe('/brand/icons/jackets.svg');
        expect(brandIconFor('Grey sweatshirt')).toBe('/brand/icons/hoodies.svg');
    });

    it('needs a Latin word to start where a word starts', () => {
        expect(brandIconFor('Escape')).toBeNull();
        expect(brandIconFor('Caps')).toBe('/brand/icons/accessories.svg');
    });

    it('has none for other names', () => {
        expect(brandIconFor('مجموعة الصيف')).toBeNull();
        expect(brandIconFor('')).toBeNull();
    });
});
