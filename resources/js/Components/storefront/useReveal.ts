import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Sections below the fold rise in once as they come into view (`.reveal`, styled in
 * StorefrontLayout). What the server rendered inside the first screen is never hidden, so nothing
 * flickers while the page hydrates. Runs in the browser only, and not for visitors who asked for
 * less motion.
 */
export function useReveal(): void {
    let observer: IntersectionObserver | null = null;
    let stopListening: (() => void) | null = null;

    function watchSections(): void {
        observer?.disconnect();

        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        observer = new IntersectionObserver(
            (entries, current) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        current.unobserve(entry.target);
                    }
                }
            },
            { rootMargin: '0px 0px -8% 0px' },
        );

        document.querySelectorAll('.reveal:not(.is-visible)').forEach((element) => {
            if (element.getBoundingClientRect().top < window.innerHeight) {
                return;
            }

            element.classList.add('reveal-ready');
            observer?.observe(element);
        });
    }

    onMounted(() => {
        watchSections();
        // Only a new page: a partial reload must not hide what the visitor already saw.
        stopListening = router.on('navigate', () => requestAnimationFrame(watchSections));
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        stopListening?.();
    });
}
