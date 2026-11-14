import { router } from '@inertiajs/vue3';
import { computed, inject, onMounted, onUnmounted, provide, ref, type ComputedRef, type Ref } from 'vue';

const COOKIE = 'sidebar_collapsed';
/** Below this width the sidebar is a drawer over the page (Tailwind `lg`). */
const DESKTOP = 1024;

export interface SidebarContext {
    /** Desktop: the sidebar shows icons only. */
    collapsed: Ref<boolean>;
    /** Mobile: the drawer is open. */
    mobileOpen: Ref<boolean>;
    isMobile: Ref<boolean>;
    /** Desktop, collapsed: the pointer is over the sidebar, which opens over the page. */
    hovered: Ref<boolean>;
    /** Texts are shown: expanded, hovered, or the mobile drawer. */
    showText: ComputedRef<boolean>;
    toggle: () => void;
    closeMobile: () => void;
}

const key = Symbol('sidebar');

function readCookie(): boolean {
    if (typeof document === 'undefined') return false;

    return document.cookie.split('; ').some((part) => part === `${COOKIE}=1`);
}

export function useSidebarProvider(): SidebarContext {
    const collapsed = ref(readCookie());
    const mobileOpen = ref(false);
    const isMobile = ref(false);
    const hovered = ref(false);

    function onResize(): void {
        isMobile.value = window.innerWidth < DESKTOP;
        if (!isMobile.value) mobileOpen.value = false;
    }

    function toggle(): void {
        if (isMobile.value) {
            mobileOpen.value = !mobileOpen.value;

            return;
        }

        collapsed.value = !collapsed.value;
        hovered.value = false;
        document.cookie = `${COOKIE}=${collapsed.value ? 1 : 0}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    }

    /** Ctrl+B (Cmd+B) folds the sidebar, as in most editors. */
    function onKeydown(event: KeyboardEvent): void {
        if (event.key.toLowerCase() === 'b' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            toggle();
        }
    }

    let stopListening: (() => void) | undefined;

    onMounted(() => {
        onResize();
        window.addEventListener('resize', onResize);
        window.addEventListener('keydown', onKeydown);
        stopListening = router.on('navigate', () => (mobileOpen.value = false));
    });

    onUnmounted(() => {
        window.removeEventListener('resize', onResize);
        window.removeEventListener('keydown', onKeydown);
        stopListening?.();
    });

    const context: SidebarContext = {
        collapsed,
        mobileOpen,
        isMobile,
        hovered,
        showText: computed(() => isMobile.value || !collapsed.value || hovered.value),
        toggle,
        closeMobile: () => (mobileOpen.value = false),
    };

    provide(key, context);

    return context;
}

export function useSidebar(): SidebarContext {
    const context = inject<SidebarContext>(key);

    if (!context) throw new Error('useSidebar() needs useSidebarProvider() in a parent (AdminLayout).');

    return context;
}
