import { onBeforeUnmount, ref, type Ref } from 'vue';

export interface CarouselOptions {
    /** How long a slide stays, in milliseconds. */
    interval: number;
    /** Whether the slides move by themselves (not for visitors who ask for less motion). */
    autoplay: () => boolean;
    /** Right-to-left pages: a swipe to the right, or the left arrow key, goes forward. */
    rtl: () => boolean;
}

/** The least a finger travels sideways for a swipe. */
const SWIPE = 45;

/**
 * A slider's state: the current and the previous slide (the one fading out), and the direction it
 * moved. Slides move by themselves every `interval`; a pause (pointer or focus in the slider) keeps
 * the time left, so a progress bar paused with it stays in step. Going to a slide by hand starts
 * its full time again. Swipes and arrow keys follow the page's reading direction.
 */
export function useCarousel(count: Ref<number>, options: CarouselOptions) {
    const current = ref(0);
    const previous = ref<number | null>(null);
    const direction = ref<1 | -1>(1);
    const paused = ref(false);
    const playing = ref(false);

    let timer: ReturnType<typeof setTimeout> | undefined;
    let startedAt = 0;
    let remaining = options.interval;
    let touchX: number | null = null;
    let touchY = 0;

    function clear(): void {
        clearTimeout(timer);
        timer = undefined;
    }

    function schedule(): void {
        clear();
        if (!playing.value || paused.value || count.value < 2) return;

        startedAt = Date.now();
        timer = setTimeout(() => step(1), remaining);
    }

    function goTo(index: number, toward?: 1 | -1): void {
        const total = count.value;
        if (total < 1) return;

        const target = ((index % total) + total) % total;
        if (target !== current.value) {
            direction.value = toward ?? (target > current.value ? 1 : -1);
            previous.value = current.value;
            current.value = target;
        }

        remaining = options.interval;
        schedule();
    }

    function step(by: 1 | -1): void {
        goTo(current.value + by, by);
    }

    function pause(): void {
        if (paused.value) return;
        paused.value = true;

        if (timer !== undefined) {
            remaining = Math.max(0, remaining - (Date.now() - startedAt));
            clear();
        }
    }

    function resume(): void {
        if (!paused.value) return;
        paused.value = false;
        schedule();
    }

    function start(): void {
        playing.value = options.autoplay() && count.value > 1;
        remaining = options.interval;
        schedule();
    }

    function stop(): void {
        playing.value = false;
        clear();
    }

    /** Forward in the reading direction: left to right, or right to left on an Arabic page. */
    function forwardFor(physical: 'left' | 'right'): 1 | -1 {
        const towardEnd = options.rtl() ? 'left' : 'right';

        return physical === towardEnd ? 1 : -1;
    }

    function onKeydown(event: KeyboardEvent): void {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

        event.preventDefault();
        step(forwardFor(event.key === 'ArrowRight' ? 'right' : 'left'));
    }

    function onTouchStart(event: TouchEvent): void {
        const touch = event.touches[0];
        touchX = touch ? touch.clientX : null;
        touchY = touch ? touch.clientY : 0;
    }

    function onTouchEnd(event: TouchEvent): void {
        const touch = event.changedTouches[0];
        if (touchX === null || !touch) return;

        const dx = touch.clientX - touchX;
        const dy = touch.clientY - touchY;
        touchX = null;

        // A swipe is mostly sideways; a scroll down the page is left alone. Swiping right shows what
        // lies to the left: the previous slide on a left-to-right page.
        if (Math.abs(dx) < SWIPE || Math.abs(dx) < Math.abs(dy) * 1.2) return;
        step(forwardFor(dx > 0 ? 'left' : 'right'));
    }

    onBeforeUnmount(clear);

    return { current, previous, direction, paused, playing, goTo, step, pause, resume, start, stop, onKeydown, onTouchStart, onTouchEnd };
}
