/** A price as the server computed it. The browser shows these texts and computes nothing. */
export interface StorePrice {
    amount: number;
    label: string;
    was: string | null;
    percent_off: number | null;
    discounted: boolean;
}

export interface StoreCard {
    id: number;
    name: string;
    url: string;
    image: string | null;
    category: string | null;
    summary: string | null;
    price: StorePrice;
}

export interface StoreCategory {
    id: number;
    name: string;
    url: string;
    image?: string | null;
}

export interface PageLink {
    slug: string;
    key: string | null;
    title: string;
    url: string;
}

export interface StoreSocial {
    label: string;
    url: string;
}

export interface StoreBranch {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    hours: string | null;
    map_url: string | null;
}

export interface StoreChannels {
    phone?: string;
    whatsapp?: string;
    email?: string;
    address?: string;
}

/** The `store` prop every storefront page gets (Modules\Store\Support\StorefrontLayout). */
export interface StoreLayoutProps {
    name: string;
    logo: string | null;
    share_image: string | null;
    announcement: string | null;
    about_url: string | null;
    tagline: string | null;
    other_locale: string;
    other_locale_name: string;
    other_locale_url: string;
    contact: StoreChannels | null;
    whatsapp: string | null;
    socials: StoreSocial[];
    branches: StoreBranch[];
    footer_pages: PageLink[];
    policy_pages: PageLink[];
    year: number;
}

export interface StoreSeo {
    title: string;
    description: string | null;
    noindex: boolean;
    canonical: string | null;
    alternates: Record<string, string>;
    x_default: string | null;
    og: {
        site_name: string;
        title: string;
        type: string;
        url: string;
        locale: string;
        description: string | null;
        image: string | null;
    };
    twitter_card: string;
    json_ld: string[];
}

export interface BrandValueText {
    title: string;
    text: string | null;
}

export interface ShopPage {
    data: StoreCard[];
    total: number;
    total_label: string;
    current_page: number;
    last_page: number;
    prev_url: string | null;
    next_url: string | null;
    pages: { label: string; url: string | null; active: boolean }[];
}

export interface AttributeValueOption {
    id: number;
    name: string;
    color: string | null;
}

export interface ProductAttribute {
    id: number;
    name: string;
    swatch: boolean;
    values: AttributeValueOption[];
}

export interface ProductVariant {
    id: number;
    values: Record<string, number>;
    price: StorePrice;
    image: string | null;
    in_stock: boolean;
    max_quantity: number;
}

export interface AddonOption {
    id: number;
    name: string;
    price: number;
    default: boolean;
    price_label: string | null;
}

export interface ProductAddon {
    id: number;
    name: string;
    multiple: boolean;
    required: boolean;
    min: number;
    max: number;
    options: AddonOption[];
}

export interface ProductOptions {
    attributes: ProductAttribute[];
    variants: ProductVariant[];
    addons: ProductAddon[];
}

/** A buyer's choice priced on the server (Modules\Store\Services\ProductPage::quote). */
export interface ProductQuote {
    selected: Record<string, number>;
    addons: Record<string, number | string | (number | string)[] | null>;
    quantity: number;
    max_quantity: number;
    variant_id: number | null;
    price: StorePrice;
    price_from: string | null;
    in_stock: boolean;
    total: number;
    total_label: string;
    problems: string[];
    whatsapp_url: string | null;
}
