import { computed, inject, onMounted, provide, ref, watch, type ComputedRef, type Ref } from 'vue';

export type Theme = 'light' | 'dark';

export interface ThemeContext {
    theme: Ref<Theme>;
    isDarkMode: ComputedRef<boolean>;
    toggleTheme: () => void;
    setTheme: (theme: Theme) => void;
}

const key = Symbol('theme');

/**
 * Light or dark admin, remembered in the browser. The root Blade view reads the same key before the
 * first paint, so a dark page never flashes white.
 */
export function useThemeProvider(): ThemeContext {
    const theme = ref<Theme>('light');
    const ready = ref(false);

    onMounted(() => {
        try {
            theme.value = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
        } catch {
            // Storage blocked: stay light.
        }
        ready.value = true;
    });

    watch([theme, ready], ([value, isReady]) => {
        if (!isReady) return;

        document.documentElement.classList.toggle('dark', value === 'dark');
        try {
            localStorage.setItem('theme', value);
        } catch {
            // Storage blocked: the choice lasts for this page only.
        }
    });

    const context: ThemeContext = {
        theme,
        isDarkMode: computed(() => theme.value === 'dark'),
        toggleTheme: () => (theme.value = theme.value === 'dark' ? 'light' : 'dark'),
        setTheme: (value) => (theme.value = value),
    };

    provide(key, context);

    return context;
}

export function useTheme(): ThemeContext {
    const context = inject<ThemeContext>(key);

    if (!context) throw new Error('useTheme() needs useThemeProvider() in a parent (AdminLayout).');

    return context;
}
