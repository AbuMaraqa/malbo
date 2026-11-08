import type { InertiaForm } from '@inertiajs/vue3';

export interface SignUpData {
    storeName: string;
    slug: string;
    businessType: string | null;
    ownerName: string;
    ownerEmail: string;
    ownerPhone: string;
    password: string;
    password_confirmation: string;
    color: string;
    font: string;
    logo: File | null;
    terms: boolean;
    /** Hidden from people; bots fill it. */
    website: string;
}

export type SignUpForm = InertiaForm<SignUpData>;

export interface FontOption {
    value: string;
    label: string;
    stack: string;
    url: string;
}

export interface BusinessType {
    value: string;
    label: string;
}

/** The state of the live address check. */
export type SlugState = 'idle' | 'checking' | 'available' | 'unavailable';
