/**
 * The Meta Pixel in the browser. The server builds every event and its id (the `marketing` prop,
 * Modules\Store\Services\Marketing\MetaEvents) and posts the same events to the Conversions API:
 * the Pixel fires each one with that id as `eventID`, so Meta keeps one of the two.
 *
 * Nothing loads unless the visitor accepted marketing cookies (`consent: 'granted'`) and the store
 * set a Pixel id. Only called once the page is mounted: it touches `window` and `document`.
 */

export interface PixelEvent {
    name: string;
    id: string;
    data: Record<string, unknown>;
}

export interface MarketingProps {
    consent: 'granted' | 'denied' | null;
    pixel_id: string | null;
    events: PixelEvent[];
}

type Fbq = {
    (...args: unknown[]): void;
    disablePushState?: boolean;
    callMethod?: (...args: unknown[]) => void;
    queue: unknown[][];
    push: Fbq;
    loaded: boolean;
    version: string;
};

type PixelWindow = Window & { fbq?: Fbq; _fbq?: Fbq };

const SCRIPT = 'https://connect.facebook.net/en_US/fbevents.js';

/** Ids already fired in this tab: a page that sends an event again (back and forward) does not count it twice. */
const fired = new Set<string>();
let initialised: string | null = null;

/**
 * Meta's loader: a queue that holds calls until the script arrives, then the script itself.
 */
function install(win: PixelWindow, doc: Document): Fbq {
    if (win.fbq) {
        return win.fbq;
    }

    const fbq = function (...args: unknown[]) {
        if (fbq.callMethod) {
            fbq.callMethod(...args);
        } else {
            fbq.queue.push(args);
        }
    } as Fbq;
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    // No PageView of its own on each history change (every Inertia visit): the server sends them.
    fbq.disablePushState = true;
    win.fbq = fbq;
    win._fbq ??= fbq;

    const script = doc.createElement('script');
    script.async = true;
    script.src = SCRIPT;
    const first = doc.getElementsByTagName('script')[0];
    if (first?.parentNode) {
        first.parentNode.insertBefore(script, first);
    } else {
        doc.head.appendChild(script);
    }

    return fbq;
}

/**
 * Loads the Pixel when allowed and fires the events it has not fired yet.
 */
export function applyMarketing(marketing: MarketingProps | null | undefined, win: Window = window, doc: Document = document): void {
    if (!marketing || marketing.consent !== 'granted' || !marketing.pixel_id) {
        return;
    }

    const pixelWindow = win as PixelWindow;
    const fbq = install(pixelWindow, doc);

    if (initialised !== marketing.pixel_id) {
        // No automatic events (clicks, metadata): only the server-made ones, each once. Meta wants
        // this before `init`.
        fbq('set', 'autoConfig', false, marketing.pixel_id);
        fbq('init', marketing.pixel_id);
        initialised = marketing.pixel_id;
    }

    for (const event of marketing.events) {
        if (fired.has(event.id)) continue;

        fired.add(event.id);
        fbq('track', event.name, event.data, { eventID: event.id });
    }
}

/** For tests: forget what this tab loaded and fired. */
export function forgetPixel(): void {
    fired.clear();
    initialised = null;
}
