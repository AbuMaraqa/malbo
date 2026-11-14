/**
 * Campaign page blocks in the admin form: what each type holds, a new empty block, the title shown on
 * a folded block, and what is sent to the server (Modules\Store\Services\CampaignPageService).
 */
export type CampaignBlockType = 'text' | 'image' | 'banner' | 'products' | 'cta' | 'countdown' | 'instagram';

export interface CampaignBlockSettings {
    link?: string | null;
    source?: 'picked' | 'category';
    product_ids?: (number | string)[];
    category_id?: number | string | null;
    limit?: number | string;
    ends_at?: string | null;
}

export interface CampaignBlockRow {
    id?: number;
    type: CampaignBlockType;
    settings: CampaignBlockSettings;
    /** Its texts in the language being edited. */
    content: Record<string, string>;
    /** A new picture for an image or banner block. */
    image: File | null;
    /** The saved picture, shown until a new one is picked. */
    image_url?: string | null;
}

/** The texts each type keeps per language (CampaignBlockType::texts). */
export const BLOCK_TEXTS: Record<CampaignBlockType, string[]> = {
    text: ['html'],
    image: ['alt', 'caption'],
    banner: ['heading', 'text', 'button_label'],
    products: ['heading'],
    cta: ['heading', 'text', 'button_label'],
    countdown: ['heading', 'text'],
    instagram: ['heading'],
};

/** The settings each type keeps. */
const BLOCK_SETTINGS: Record<CampaignBlockType, (keyof CampaignBlockSettings)[]> = {
    text: [],
    image: ['link'],
    banner: ['link'],
    products: ['source', 'product_ids', 'category_id', 'limit'],
    cta: ['link'],
    countdown: ['ends_at'],
    instagram: ['limit'],
};

export function hasImage(type: CampaignBlockType): boolean {
    return type === 'image' || type === 'banner';
}

export function newBlock(type: CampaignBlockType): CampaignBlockRow {
    const settings: CampaignBlockSettings = {};

    if (type === 'products') Object.assign(settings, { source: 'picked', product_ids: [], category_id: null, limit: 8 });
    if (type === 'instagram') settings.limit = 8;
    if (BLOCK_SETTINGS[type].includes('link')) settings.link = '';
    if (type === 'countdown') settings.ends_at = '';

    return {
        type,
        settings,
        content: Object.fromEntries(BLOCK_TEXTS[type].map((field) => [field, ''])),
        image: null,
        image_url: null,
    };
}

/** "Banner: Summer sale": the type, then its first text (rich text without its tags), cut short. */
export function blockLabel(block: CampaignBlockRow, typeLabel: string): string {
    const first = BLOCK_TEXTS[block.type]
        .map((field) => (block.content[field] ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim())
        .find((text) => text !== '');

    if (!first) return typeLabel;

    return `${typeLabel}: ${first.length > 40 ? `${first.slice(0, 40)}…` : first}`;
}

/** What the server reads of each block: its id, type, its own settings and texts, a new picture. */
export function toRequestBlocks(blocks: CampaignBlockRow[]): Record<string, unknown>[] {
    return blocks.map((block) => ({
        ...(block.id ? { id: block.id } : {}),
        type: block.type,
        settings: Object.fromEntries(BLOCK_SETTINGS[block.type].filter((key) => block.settings[key] !== undefined).map((key) => [key, block.settings[key]])),
        content: Object.fromEntries(BLOCK_TEXTS[block.type].map((field) => [field, block.content[field] ?? ''])),
        ...(hasImage(block.type) && block.image ? { image: block.image } : {}),
    }));
}
