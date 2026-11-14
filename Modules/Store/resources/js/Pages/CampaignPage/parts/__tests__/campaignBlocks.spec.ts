import { describe, expect, it } from 'vitest';

import { blockLabel, newBlock, toRequestBlocks } from '../campaignBlocks';

describe('campaignBlocks', () => {
    it('starts each type with its own texts and settings', () => {
        expect(newBlock('banner')).toEqual({ type: 'banner', settings: { link: '' }, content: { heading: '', text: '', button_label: '' }, image: null, image_url: null });
        expect(newBlock('products').settings).toEqual({ source: 'picked', product_ids: [], category_id: null, limit: 8 });
        expect(newBlock('text').content).toEqual({ html: '' });
        expect(newBlock('countdown').settings).toEqual({ ends_at: '' });
    });

    it('names a block by its type and first text', () => {
        const banner = newBlock('banner');
        expect(blockLabel(banner, 'Banner')).toBe('Banner');

        banner.content.heading = 'Summer sale';
        expect(blockLabel(banner, 'Banner')).toBe('Banner: Summer sale');

        const text = newBlock('text');
        text.content.html = '<p>A very long paragraph that keeps going well past the limit</p>';
        expect(blockLabel(text, 'Text')).toBe('Text: A very long paragraph that keeps going w…');
    });

    it('sends only what each type keeps, and a picture only when one was picked', () => {
        const file = new File(['x'], 'banner.jpg', { type: 'image/jpeg' });
        const banner = { ...newBlock('banner'), id: 4, image: file, settings: { link: '/ar/shop', product_ids: [1] } };
        const text = { ...newBlock('text'), image: file };

        expect(toRequestBlocks([banner, text])).toEqual([
            { id: 4, type: 'banner', settings: { link: '/ar/shop' }, content: { heading: '', text: '', button_label: '' }, image: file },
            { type: 'text', settings: {}, content: { html: '' } },
        ]);
    });
});
