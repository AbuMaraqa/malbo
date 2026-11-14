/**
 * The storefront's look as Tailwind classes: a clean, bright shop in the store's own color, with
 * white panels on soft grey. They live in utilities, in the CSS the first paint already has (SSR),
 * so nothing flashes unstyled. The store's color is `store-*`; the neutrals are CSS variables set
 * by StorefrontLayout.
 */

const panelBase = 'border border-line shadow-[0_10px_30px_-18px_rgb(22_28_45/0.25)]';

/** White panels: menus, labels over photos, badges. */
export const panel = `${panelBase} bg-white`;

/** The header's bar once the page scrolls under it. */
export const panelFirm = 'bg-white shadow-[0_6px_24px_-16px_rgb(22_28_45/0.35)]';

/** Carries text over busy photos: signature, story and members cards. */
export const panelStrong = `${panelBase} bg-white/95`;

const buttonBase =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md px-6 py-2.5 font-semibold tracking-wide transition duration-300 disabled:cursor-not-allowed disabled:opacity-50';

export const btnBrand = `${buttonBase} border border-store bg-store text-store-contrast hover:border-store-700 hover:bg-store-700`;

export const btnDark = `${buttonBase} border border-ink bg-ink text-white hover:bg-store hover:border-store hover:text-store-contrast`;

export const btnOutline = `${buttonBase} border border-line bg-white text-ink hover:border-store hover:bg-store hover:text-store-contrast`;

export const btnLg = 'min-h-12 px-8 text-[1rem]';

export const eyebrow = 'inline-block text-[0.8125rem] font-semibold tracking-[0.12em] text-store-600 uppercase';

export const sectionTitle = 'text-[1.625rem] leading-[1.3] font-bold sm:text-[1.875rem]';

/** The muted line under a centered section title. */
export const sectionLead = 'mx-auto mt-3 max-w-xl text-[0.9375rem] leading-7 text-muted';

export const linkArrow = 'inline-flex items-center gap-2 text-[0.9375rem] font-semibold text-ink transition-colors hover:text-store-600';

/** The page width every storefront section shares. */
export const container = 'mx-auto w-full max-w-310 px-4 sm:px-6';

const chipBase = 'inline-flex min-h-10 items-center rounded-md border px-4 text-[0.9375rem] whitespace-nowrap transition-colors';

export const chip = `${chipBase} border-line bg-white hover:border-store hover:text-store-600`;

export const chipActive = `${chipBase} border-store bg-store font-semibold text-store-contrast`;

const pageLinkBase = 'inline-grid h-10 min-w-10 cursor-pointer place-items-center rounded-md border px-3 transition-colors';

export const pageLink = `${pageLinkBase} border-line bg-white hover:border-store hover:bg-store hover:text-store-contrast`;

export const pageLinkActive = `${pageLinkBase} border-store bg-store font-semibold text-store-contrast`;

export const fieldLabel = 'mb-1.5 block text-[0.9375rem] font-semibold';

export const field =
    'block min-h-11 w-full rounded-md border border-line bg-white px-3.5 text-base transition-colors focus:border-store focus:shadow-[0_0_0_3px_var(--color-store-100)] focus:outline-none aria-invalid:border-(--danger)';

export const fieldError = 'mt-1.5 text-sm text-(--danger)';

export const helpLink = '-mx-2.5 block rounded-md px-2.5 py-1.5 text-(--ink-soft) hover:bg-(--sand-light) hover:text-store-600';

/** A white card on the grey canvas: cart, checkout, account. */
export const card = 'rounded-lg border border-line bg-white';

/** The owner's rich text, sanitized on the server (RichText). */
export const prose =
    'text-[1.0625rem] leading-[1.9] text-(--ink-soft) [&_a]:text-store-600 [&_a]:underline [&_h2]:mt-[1.2em] [&_h2]:mb-[0.5em] [&_h2]:font-bold [&_h2]:text-ink [&_h3]:mt-[1.2em] [&_h3]:mb-[0.5em] [&_h3]:font-bold [&_h3]:text-ink [&_img]:rounded-lg [&_ol]:mb-[1em] [&_ol]:list-decimal [&_ol]:ps-[1.5em] [&_p]:mb-[1em] [&_ul]:mb-[1em] [&_ul]:list-disc [&_ul]:ps-[1.5em]';

/** Information pages: each h2 opens a section, and the sticky header must not cover it. */
export const proseSections =
    '[&_h2]:mt-[2em] [&_h2]:mb-[0.6em] [&_h2]:scroll-mt-36 [&_h2]:border-t [&_h2]:border-line [&_h2]:pt-[1.5em] [&_h2]:text-[1.375rem] [&_>_h2:first-child]:mt-0 [&_>_h2:first-child]:border-t-0 [&_>_h2:first-child]:pt-0 [&_>_p:first-child]:text-[1.1875rem] [&_>_p:first-child]:text-ink [&_li::marker]:text-store';
