/**
 * The storefront's look as Tailwind classes, after the MALBO brand guidelines: cream paper (60%),
 * the store's maroon for actions and dark bands (30%), gold for accents (10%; `--gold-ink` is gold
 * dark enough for small text on cream). Soft, curved shapes like the mark's petals: pill buttons and
 * chips, rounded cards. Arabic is never spaced out or set in capitals; Latin labels keep their small
 * spaced capitals (`ltr:`). They live in utilities, in the CSS the first paint already has (SSR), so
 * nothing flashes unstyled. The store's color is `store-*`; the neutrals are CSS variables (app.css).
 */

const panelBase = 'border border-line shadow-[0_10px_30px_-18px_rgb(42_21_26/0.25)]';

/** Light cream panels: menus, labels over photos. */
export const panel = `${panelBase} rounded-xl bg-(--shell)`;

/** The header's bar once the page scrolls under it. */
export const panelFirm = 'bg-(--paper) shadow-[0_6px_24px_-16px_rgb(42_21_26/0.35)]';

/** Carries text over busy photos: signature, story and members cards. */
export const panelStrong = `${panelBase} bg-(--shell)/95`;

const buttonBase =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-full px-7 py-2.5 text-[0.875rem] font-semibold ltr:text-[0.8125rem] ltr:tracking-[0.08em] ltr:uppercase transition duration-300 disabled:cursor-not-allowed disabled:opacity-50';

export const btnBrand = `${buttonBase} border border-store bg-store text-store-contrast hover:border-store-700 hover:bg-store-700`;

/** The deep maroon button for light surfaces; gold on hover. */
export const btnDark = `${buttonBase} border border-(--night) bg-(--night) text-white hover:border-(--gold) hover:bg-(--gold) hover:text-(--night)`;

/** The gold call to action on maroon surfaces (the footer, the members band). */
export const btnGold = `${buttonBase} border border-(--gold) bg-(--gold) text-(--night) hover:border-(--shell) hover:bg-(--shell)`;

export const btnOutline = `${buttonBase} border border-line bg-(--shell) text-ink hover:border-store hover:bg-store hover:text-store-contrast`;

export const btnLg = 'min-h-12 px-9 text-[0.875rem]';

export const eyebrow = 'inline-block text-[0.8125rem] font-semibold ltr:tracking-[0.12em] text-(--gold-ink) ltr:uppercase';

export const sectionTitle = 'text-[1.75rem] leading-[1.35] font-bold sm:text-[2.25rem]';

/** The muted line under a centered section title. */
export const sectionLead = 'mx-auto mt-3 max-w-xl text-[0.9375rem] leading-7 text-muted';

export const linkArrow = 'inline-flex items-center gap-2 text-[0.9375rem] font-semibold text-ink transition-colors hover:text-store-600';

/** The page width every storefront section shares. */
export const container = 'mx-auto w-full max-w-310 px-4 sm:px-6';

const chipBase = 'inline-flex min-h-10 items-center rounded-full border px-4 text-[0.9375rem] whitespace-nowrap transition-colors';

export const chip = `${chipBase} border-line bg-(--shell) hover:border-store hover:text-store-600`;

export const chipActive = `${chipBase} border-store bg-store font-semibold text-store-contrast`;

const pageLinkBase = 'inline-grid h-10 min-w-10 cursor-pointer place-items-center rounded-full border px-3 transition-colors';

export const pageLink = `${pageLinkBase} border-line bg-(--shell) hover:border-store hover:bg-store hover:text-store-contrast`;

export const pageLinkActive = `${pageLinkBase} border-store bg-store font-semibold text-store-contrast`;

export const fieldLabel = 'mb-1.5 block text-[0.9375rem] font-semibold';

export const field =
    'block min-h-11 w-full rounded-xl border border-line bg-(--shell) px-3.5 text-base transition-colors focus:border-store focus:shadow-[0_0_0_3px_var(--color-store-200)] focus:outline-none aria-invalid:border-(--danger)';

export const fieldError = 'mt-1.5 text-sm text-(--danger)';

export const helpLink = '-mx-2.5 block rounded-md px-2.5 py-1.5 text-(--ink-soft) hover:bg-(--sand-light) hover:text-store-600';

/** A light cream card on the cream paper: cart, checkout, account. */
export const card = 'rounded-2xl border border-line bg-(--shell)';

/** The owner's rich text, sanitized on the server (RichText). */
export const prose =
    'text-[1.0625rem] leading-[1.9] text-(--ink-soft) [&_a]:text-store-600 [&_a]:underline [&_h2]:mt-[1.2em] [&_h2]:mb-[0.5em] [&_h2]:font-bold [&_h2]:text-ink [&_h3]:mt-[1.2em] [&_h3]:mb-[0.5em] [&_h3]:font-bold [&_h3]:text-ink [&_img]:rounded-lg [&_ol]:mb-[1em] [&_ol]:list-decimal [&_ol]:ps-[1.5em] [&_p]:mb-[1em] [&_ul]:mb-[1em] [&_ul]:list-disc [&_ul]:ps-[1.5em]';

/** Information pages: each h2 opens a section, and the sticky header must not cover it. */
export const proseSections =
    '[&_h2]:mt-[2em] [&_h2]:mb-[0.6em] [&_h2]:scroll-mt-36 [&_h2]:border-t [&_h2]:border-line [&_h2]:pt-[1.5em] [&_h2]:text-[1.375rem] [&_>_h2:first-child]:mt-0 [&_>_h2:first-child]:border-t-0 [&_>_h2:first-child]:pt-0 [&_>_p:first-child]:text-[1.1875rem] [&_>_p:first-child]:text-ink [&_li::marker]:text-store';
