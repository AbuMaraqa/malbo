/** A price as the server computed it. The browser shows these texts and computes nothing. */
export interface StorePrice {
    amount: number;
    label: string;
    was: string | null;
    percent_off: number | null;
    discounted: boolean;
}

/**
 * A badge as the server decided it (Modules\Store\Services\ProductBadges). `tone` picks the look; a
 * label badge brings its own colors.
 */
export interface StoreBadge {
    key: string;
    label: string;
    tone: 'label' | 'accent' | 'ink' | 'sale' | 'warning' | 'muted';
    text_color?: string | null;
    background_color?: string | null;
}

export interface StoreCard {
    id: number;
    name: string;
    url: string;
    image: string | null;
    category: string | null;
    summary: string | null;
    price: StorePrice;
    badges: StoreBadge[];
    /** The size values on the variants a buyer can see (Modules\Store\Support\CardSizes); empty without sizes. */
    sizes?: CardSize[];
}

/** A size on a card: `available` false when no variant in it can be ordered now (struck through). */
export interface CardSize {
    name: string;
    available: boolean;
}

/** The shop's search, filters and sort, as the server cleaned them from the URL. */
export interface ShopFilterState {
    q: string;
    sort: string;
    /** attribute id => chosen value ids */
    attr: Record<string, number[]>;
    price_min: number | null;
    price_max: number | null;
}

export interface FacetValue {
    id: number;
    name: string;
    color: string | null;
    /** Products this value would show with the other filters. */
    count: number;
    selected: boolean;
}

export interface FacetAttribute {
    id: number;
    name: string;
    swatch: boolean;
    values: FacetValue[];
}

/** What the shop can be filtered by now (Modules\Store\Services\StorefrontShop). */
export interface ShopFacets {
    attributes: FacetAttribute[];
    /** The displayed price's bounds over what the other filters leave; null without products. */
    price: { min: number | null; max: number | null };
}

/** A suggestion of the header's search box (Modules\Store\Services\StorefrontSearch). */
export interface SearchSuggestions {
    products: { name: string; image: string | null; price: string; was: string | null; url: string }[];
    categories: { name: string; url: string }[];
}

/** One line of the product page's "Details" section. */
export interface ProductDetail {
    key: string;
    label: string;
    value: string;
}

/** A product image and the color (attribute value id) it shows, if any. */
export interface ProductImage {
    url: string;
    color: number | null;
}

/** The product video: an embed address (YouTube without cookies, Vimeo) or an .mp4 file. */
export interface ProductVideo {
    type: 'youtube' | 'vimeo' | 'file';
    src: string;
}

export interface SizeGuide {
    title: string;
    /** Sanitized on the server (RichText). */
    html: string;
}

export interface StoreCategory {
    id: number;
    name: string;
    url: string;
    image?: string | null;
}

/** An active root category as a tile on the categories page. */
export interface StoreCategoryTile {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    image: string | null;
    url: string;
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
    /** The MALBO logo for maroon surfaces, while the owner has not uploaded a logo of their own. */
    logo_light: string | null;
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
    /** Active root categories, for the header's menu and the footer. */
    categories: StoreCategory[];
    /** The categories page, or null while there is no active category. */
    categories_url: string | null;
    /** The members circle's page, or null while the store does not take members. */
    members_url: string | null;
    /** Pieces in this browser's cart, and the cart page. */
    cart_count: number;
    cart_url: string;
    /** The header's account menu, or its sign-in link. */
    account: StoreAccount;
    /** Products on the signed-in buyer's wishlist (empty for a guest): what the hearts show. */
    wishlist: number[];
    /** The way into the wholesale portal, or null while no module serves it. */
    wholesale: StoreWholesale | null;
    year: number;
}

/** Links to the wholesale portal (StorefrontLayout::wholesale). Never a price. */
export interface StoreWholesale {
    /** The signed-in account is a merchant (any status): it goes to the portal, not the join form. */
    merchant: boolean;
    portal_url: string;
    apply_url: string;
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
    /** The attribute marked as the size: the size guide sits by it. */
    size: boolean;
    values: AttributeValueOption[];
}

export interface ProductVariant {
    id: number;
    values: Record<string, number>;
    price: StorePrice;
    image: string | null;
    in_stock: boolean;
    max_quantity: number;
    details: ProductDetail[];
    /** Its displayed price is discounted: the page's offer badge follows the chosen variation. */
    on_offer: boolean;
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

/** A cart line priced on the server (Modules\Store\Services\CartService::lines). */
export interface CartLine {
    id: number;
    product_id: number;
    name: string;
    options: { name: string; value: string }[];
    image: string | null;
    url: string | null;
    sku: string | null;
    price: StorePrice;
    unit_price: number;
    quantity: number;
    max_quantity: number;
    total: number;
    total_label: string;
    /** False when the product is gone, unpublished, out of stock or over the limit: see `problem`. */
    available: boolean;
    problem: string | null;
    /** A set or bundle: `options` are its components with the size chosen for each. */
    bundle?: boolean;
}

/** One piece of a set or bundle on its page (Modules\Store\Services\BundlePage::components). */
export interface BundleComponentOption {
    id: number;
    name: string;
    /** Pieces of it in one bundle. */
    quantity: number;
    image: string | null;
    /** The buyer picks its size or colour; false for a product without variants or a fixed variant. */
    choosable: boolean;
    /** A fixed variant's values ("Black / M"). */
    fixed: string | null;
    /** Some choice of it can be ordered now. */
    available: boolean;
    attributes: ProductAttribute[];
    variants: { id: number; values: Record<string, number>; in_stock: boolean; image: string | null }[];
}

/** An Instagram post on the storefront (Modules\Store\Contracts\InstagramFeedSource::posts). */
export interface InstagramPostCard {
    id: number | string;
    image: string | null;
    url: string;
    caption: string | null;
    products: { name: string; url: string; image: string | null }[];
}

/** A piece of "complete the look" (Modules\Store\Services\ProductPage::look). */
export interface LookItem {
    id: number;
    name: string;
    url: string;
    image: string | null;
    price: StorePrice;
    in_stock: boolean;
    /** Its sizes (and colours); empty for a piece without variants. */
    variants: { id: number; label: string; in_stock: boolean }[];
}

/** A set or bundle's choice priced on the server (BundlePage::quote). */
export interface BundleQuote {
    /** Component id => the variant chosen. */
    components: Record<string, number>;
    quantity: number;
    max_quantity: number;
    price: StorePrice;
    in_stock: boolean;
    total: number;
    total_label: string;
    problems: string[];
    ready: boolean;
    whatsapp_url: string | null;
}

/** Checkout's money, computed on the server (Modules\Store\Services\CheckoutService::quote). */
export interface CheckoutQuote {
    subtotal: number;
    subtotal_label: string;
    discount: number;
    discount_label: string;
    delivery_fee: number;
    /** Null until a deliverable city is chosen. */
    delivery_fee_label: string | null;
    free_shipping: boolean;
    free_shipping_threshold_label: string | null;
    free_shipping_remaining_label: string | null;
    free_shipping_progress: number | null;
    total: number;
    total_label: string;
    coupon: { code: string; valid: boolean; message: string | null } | null;
    city_chosen: boolean;
    deliverable: boolean;
    zone: { name: string; fee_label: string; eta: string } | null;
}

/** A line as checkout lists it. */
export interface CheckoutLine {
    id: number;
    name: string;
    options: { name: string; value: string }[];
    /** A set or bundle: its options are its pieces and the size chosen for each. */
    bundle?: boolean;
    image: string | null;
    quantity: number;
    total_label: string;
}

export interface DeliverableCity {
    value: number;
    label: string;
    districts: { value: number; label: string }[];
}

/** A stage of an order's path, as the tracking page shows it. */
export interface TrackingStage {
    key: string;
    label: string;
    reached: boolean;
    current: boolean;
    date: string | null;
    /** `done`/`pending` on the way; a final status ends in `danger` (cancelled, rejected, returned) or `info` (exchanged). */
    tone: 'done' | 'pending' | 'danger' | 'info';
}

/** What the tracking page may show about an order (Modules\Store\Services\OrderTracking). */
export interface TrackingResult {
    number: string;
    placed_at: string | null;
    status: { key: string; label: string };
    final: boolean;
    timeline: TrackingStage[];
    /** `bundle`: the set a piece was sold in ("Summer set ×2"), or null. */
    lines: { name: string; bundle?: string | null; options: { name: string; value: string }[]; quantity: number; total_label: string }[];
    totals: { subtotal_label: string; discount_label: string | null; delivery_fee_label: string; total_label: string };
    /** The courier carrying the parcel, once it is shipped. */
    shipment: { courier: string; tracking_number: string | null } | null;
}

/** Who is signed in on the storefront and where the account's pages are (StorefrontLayout::account). */
export interface StoreAccount {
    signed_in: boolean;
    name: string | null;
    login_url: string;
    register_url: string;
    account_url: string;
    orders_url: string;
    wishlist_url: string;
    logout_url: string;
}

/** A delivery address saved in the account. */
export interface SavedAddress {
    id: number;
    label: string | null;
    recipient_name: string;
    phone: string;
    city_id: number | null;
    city: string | null;
    district_id: number | null;
    district: string | null;
    address_line: string;
    is_default: boolean;
}

/** One row of the account's orders. */
export interface AccountOrderRow {
    number: string;
    placed_at: string | null;
    status: { key: string; label: string };
    items_count: number;
    total_label: string;
    url: string;
}

export interface AccountOrderPage {
    data: AccountOrderRow[];
    current_page: number;
    last_page: number;
    prev_url: string | null;
    next_url: string | null;
    total: number;
}

/** A return or exchange request as its buyer sees it: the status, never a staff note. */
export interface AccountReturn {
    id: number;
    type: string;
    status: { key: string; label: string };
    reason: string;
    customer_note: string | null;
    refund_label: string | null;
    created_at: string | null;
    /** Still waiting for staff: the buyer may withdraw it. */
    can_cancel: boolean;
    lines: { name: string; options: { name: string; value: string }[]; quantity: number; exchange_for: string | null }[];
    history: { status: string; date: string | null }[];
}

/** What the return form offers for a delivered order (Modules\Store\Services\AccountOrders). */
export interface ReturnForm {
    lines: {
        order_item_id: number;
        name: string;
        options: { name: string; value: string }[];
        max: number;
        exchange_options: { value: number; label: string }[];
    }[];
    types: { value: string; label: string }[];
    reasons: { value: string; label: string }[];
    window_ends: string;
}

/** An order in the account: what tracking shows, plus the coupon and the return requests. */
export interface AccountOrderDetail extends TrackingResult {
    coupon_code: string | null;
    returns: AccountReturn[];
    return_form: ReturnForm | null;
    return_blocked: string | null;
}

/** A review as the product page shows it (Modules\Store\Services\ProductReviews::row). */
export interface StoreReview {
    id: number;
    rating: number;
    title: string | null;
    body: string;
    author: string;
    verified: boolean;
    date: string | null;
    photos: { id: number; url: string }[];
    reply: string | null;
}

export interface StoreReviewPage {
    data: StoreReview[];
    meta: { current_page: number; last_page: number; total: number };
}

export interface ReviewSummary {
    average: number | null;
    count: number;
}

export interface ReviewFormState {
    signed_in: boolean;
    eligible: boolean;
    login_url: string;
    submit_url: string;
    review: (StoreReview & { status: string; status_label: string }) | null;
}
