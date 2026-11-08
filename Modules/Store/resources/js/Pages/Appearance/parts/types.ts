import type { InertiaForm } from '@inertiajs/vue3';

export interface Localized {
    ar: string;
    en: string;
}

export interface BrandValue {
    title: Localized;
    text: Localized;
}

export interface AppearanceSettings {
    primary_color: string;
    font: string;
    announcement: Localized;
    hero_eyebrow: Localized;
    hero_title: Localized;
    hero_subtitle: Localized;
    story_title: Localized;
    story_text: Localized;
    values: BrandValue[];
    membership_enabled: boolean;
    membership_title: Localized;
    membership_text: Localized;
    whatsapp: string | null;
    phone: string | null;
    email: string | null;
    address: Localized;
    instagram_url: string | null;
    facebook_url: string | null;
    tiktok_url: string | null;
    x_url: string | null;
}

export type ImageField = 'logo' | 'favicon' | 'hero_image' | 'story_image';

export interface AppearanceFormData extends AppearanceSettings {
    _method: 'put';
    logo: File | null;
    favicon: File | null;
    hero_image: File | null;
    story_image: File | null;
    removed_images: ImageField[];
}

export type AppearanceForm = InertiaForm<AppearanceFormData>;
export type SavedImages = Record<ImageField, string | null>;
