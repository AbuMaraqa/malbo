import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import type { CampaignBlockRow } from '../campaignBlocks';
import CampaignBlockEditor from '../CampaignBlockEditor.vue';

const blockTypes = [
    { value: 'text', label: 'Text' },
    { value: 'banner', label: 'Banner' },
    { value: 'cta', label: 'Call to action' },
];

function mounted(blocks: CampaignBlockRow[] = []) {
    const state = { blocks };
    const wrapper = mount(CampaignBlockEditor, {
        props: {
            modelValue: blocks,
            'onUpdate:modelValue': (value: CampaignBlockRow[]) => {
                state.blocks = value;
                void wrapper.setProps({ modelValue: value });
            },
            blockTypes,
            categories: [],
            errors: {},
        },
        global: { stubs: { RichEditor: true } },
    });

    return { wrapper, state };
}

describe('CampaignBlockEditor', () => {
    it('adds a block of the type chosen', async () => {
        const { wrapper, state } = mounted();

        await wrapper.find('select').setValue('cta');
        await wrapper.find('[data-add-block]').trigger('click');

        expect(state.blocks).toHaveLength(1);
        expect(state.blocks[0].type).toBe('cta');
        expect(state.blocks[0].content).toEqual({ heading: '', text: '', button_label: '' });
    });

    it('names each block by its type and first text', () => {
        const { wrapper } = mounted([{ type: 'banner', settings: { link: '' }, content: { heading: 'Summer sale', text: '', button_label: '' }, image: null }]);

        expect(wrapper.text()).toContain('Banner: Summer sale');
    });

    it('shows the fields of the block type', async () => {
        const { wrapper } = mounted([{ type: 'cta', settings: { link: '/ar/shop' }, content: { heading: 'Go', text: '', button_label: 'Shop' }, image: null }]);

        // Saved rows start open: the call to action's link is there.
        expect((wrapper.find('input[placeholder="/ar/shop"]').element as HTMLInputElement).value).toBe('/ar/shop');
    });

    it('shows a block error at its own field', () => {
        const wrapper = mount(CampaignBlockEditor, {
            props: {
                modelValue: [{ type: 'cta', settings: { link: 'javascript:x' }, content: { heading: '', text: '', button_label: '' }, image: null }],
                blockTypes,
                categories: [],
                errors: { 'blocks.0.settings.link': 'The link format is invalid.' },
            },
            global: { stubs: { RichEditor: true } },
        });

        expect(wrapper.text()).toContain('The link format is invalid.');
    });
});
