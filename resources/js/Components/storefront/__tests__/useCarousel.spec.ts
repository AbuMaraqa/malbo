import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';

import { useCarousel } from '../hero/useCarousel';

function setup(total = 3, { autoplay = true, rtl = false } = {}) {
    const count = ref(total);
    let carousel!: ReturnType<typeof useCarousel>;

    mount(
        defineComponent({
            setup() {
                carousel = useCarousel(count, { interval: 1000, autoplay: () => autoplay, rtl: () => rtl });

                return () => h('div');
            },
        }),
    );

    return carousel;
}

function touch(carousel: ReturnType<typeof useCarousel>, fromX: number, toX: number, dy = 0): void {
    carousel.onTouchStart({ touches: [{ clientX: fromX, clientY: 100 }] } as unknown as TouchEvent);
    carousel.onTouchEnd({ changedTouches: [{ clientX: toX, clientY: 100 + dy }] } as unknown as TouchEvent);
}

describe('useCarousel', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('moves on by itself and wraps around', () => {
        const carousel = setup(2);
        carousel.start();

        vi.advanceTimersByTime(1000);
        expect([carousel.current.value, carousel.previous.value]).toEqual([1, 0]);

        vi.advanceTimersByTime(1000);
        expect(carousel.current.value).toBe(0);
    });

    it('keeps the time left across a pause', () => {
        const carousel = setup();
        carousel.start();

        vi.advanceTimersByTime(600);
        carousel.pause();
        vi.advanceTimersByTime(5000);
        expect(carousel.current.value).toBe(0);

        carousel.resume();
        vi.advanceTimersByTime(399);
        expect(carousel.current.value).toBe(0);
        vi.advanceTimersByTime(1);
        expect(carousel.current.value).toBe(1);
    });

    it('gives a slide chosen by hand its full time', () => {
        const carousel = setup();
        carousel.start();

        vi.advanceTimersByTime(900);
        carousel.goTo(2);
        expect(carousel.direction.value).toBe(1);
        vi.advanceTimersByTime(900);
        expect(carousel.current.value).toBe(2);
        vi.advanceTimersByTime(100);
        expect(carousel.current.value).toBe(0);
    });

    it('never moves by itself when asked for less motion, or with one slide', () => {
        const still = setup(3, { autoplay: false });
        still.start();
        vi.advanceTimersByTime(5000);
        expect([still.current.value, still.playing.value]).toEqual([0, false]);

        const single = setup(1);
        single.start();
        expect(single.playing.value).toBe(false);
    });

    it('follows swipes and arrow keys in the reading direction', () => {
        const ltr = setup(3, { autoplay: false });
        touch(ltr, 200, 100); // finger to the left: the next slide
        expect(ltr.current.value).toBe(1);
        touch(ltr, 100, 200);
        expect(ltr.current.value).toBe(0);

        const rtl = setup(3, { autoplay: false, rtl: true });
        touch(rtl, 100, 200); // finger to the right on an Arabic page: the next slide
        expect(rtl.current.value).toBe(1);
        rtl.onKeydown(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        expect(rtl.current.value).toBe(0);
        rtl.onKeydown(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        expect(rtl.current.value).toBe(1);
    });

    it('leaves short or mostly vertical moves to the page', () => {
        const carousel = setup(3, { autoplay: false });

        touch(carousel, 200, 180);
        touch(carousel, 200, 120, 200);
        expect(carousel.current.value).toBe(0);
    });
});
