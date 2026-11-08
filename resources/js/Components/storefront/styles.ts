/**
 * The storefront's look as Tailwind classes: light liquid glass in the store's own color. They live
 * in utilities, in the CSS the first paint already has (SSR), so nothing flashes unstyled.
 * The store's color is `store-*`; the warm neutrals are CSS variables set by StorefrontLayout.
 */

const glassBase =
    'border border-white/75 backdrop-blur-[24px] backdrop-saturate-[1.8] shadow-[inset_0_1px_0_rgb(255_255_255/0.9),inset_0_-1px_0_rgb(255_255_255/0.3),0_12px_40px_-16px_rgb(24_25_26/0.22)] not-supports-[backdrop-filter:blur(1px)]:bg-white/94 [@media(prefers-reduced-transparency:reduce)]:bg-white/94 [@media(prefers-reduced-transparency:reduce)]:backdrop-blur-none';

/** Frosted panels: the header, the hero, category labels and product badges. */
export const glass = `${glassBase} bg-white/55`;

/** The header once the page scrolls under it. */
export const glassFirm = `${glassBase} bg-white/78`;

/** Carries text over busy photos: the signature, story and members cards. */
export const glassStrong = `${glassBase} bg-white/72`;

const buttonBase =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-full px-7 py-3 font-bold transition duration-200 disabled:cursor-not-allowed disabled:opacity-50';

export const btnBrand = `${buttonBase} bg-store text-store-contrast shadow-[0_10px_24px_-12px_var(--color-store)] hover:-translate-y-px hover:bg-store-600 motion-reduce:hover:translate-y-0`;

export const btnOutline = `${buttonBase} border border-(--ink-30) hover:bg-white/60`;

export const btnLg = 'min-h-13 px-9 text-[1.0625rem]';

export const eyebrow = "inline-flex items-center gap-3 text-[0.8125rem] font-bold tracking-[0.08em] text-store-700 uppercase before:h-px before:w-7 before:bg-current before:content-['']";

export const sectionTitle = 'text-[1.75rem] leading-[1.3] font-bold sm:text-[2rem]';

export const linkArrow = 'inline-flex items-center gap-2 text-[0.9375rem] font-bold text-store-700 hover:underline hover:underline-offset-4';

const chipBase = 'inline-flex min-h-10 items-center rounded-full border px-4 text-[0.9375rem] whitespace-nowrap';

export const chip = `${chipBase} border-line bg-white`;

export const chipActive = `${chipBase} border-store bg-store font-bold text-store-contrast`;

const pageLinkBase = 'inline-grid h-11 min-w-11 cursor-pointer place-items-center rounded-md border px-3';

export const pageLink = `${pageLinkBase} border-line bg-white`;

export const pageLinkActive = `${pageLinkBase} border-store bg-store font-bold text-store-contrast`;

export const fieldLabel = 'mb-1.5 block text-[0.9375rem] font-bold';

export const field =
    'block min-h-12 w-full rounded-md border border-(--ink-18) bg-white px-3.5 text-base transition-colors focus:border-store focus:shadow-[0_0_0_3px_var(--color-store-100)] focus:outline-none aria-invalid:border-(--danger)';

export const fieldError = 'mt-1.5 text-sm text-(--danger)';

export const helpLink = '-mx-2.5 block rounded-md px-2.5 py-1.5 text-(--ink-soft) hover:bg-(--sand-light) hover:text-ink';

/** The owner's rich text, sanitized on the server (RichText). */
export const prose =
    'text-[1.0625rem] leading-[1.9] text-(--ink-soft) [&_a]:text-store-700 [&_a]:underline [&_h2]:mt-[1.2em] [&_h2]:mb-[0.5em] [&_h2]:font-bold [&_h2]:text-ink [&_h3]:mt-[1.2em] [&_h3]:mb-[0.5em] [&_h3]:font-bold [&_h3]:text-ink [&_img]:rounded-lg [&_ol]:mb-[1em] [&_ol]:list-decimal [&_ol]:ps-[1.5em] [&_p]:mb-[1em] [&_ul]:mb-[1em] [&_ul]:list-disc [&_ul]:ps-[1.5em]';

/** Information pages: each h2 opens a section, and the sticky header must not cover it. */
export const proseSections =
    '[&_h2]:mt-[2em] [&_h2]:mb-[0.6em] [&_h2]:scroll-mt-28 [&_h2]:border-t [&_h2]:border-line [&_h2]:pt-[1.5em] [&_h2]:text-[1.375rem] [&_>_h2:first-child]:mt-0 [&_>_h2:first-child]:border-t-0 [&_>_h2:first-child]:pt-0 [&_>_p:first-child]:text-[1.1875rem] [&_>_p:first-child]:text-ink [&_li::marker]:text-store';
