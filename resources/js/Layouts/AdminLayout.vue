<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ConfigProvider } from 'reka-ui';
import { computed } from 'vue';

import AppFooter from '@/Components/layout/AppFooter.vue';
import AppHeader from '@/Components/layout/AppHeader.vue';
import AppSidebar from '@/Components/layout/AppSidebar.vue';
import SectionNav from '@/Components/layout/SectionNav.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import Toasts from '@/Components/ui/Toasts.vue';
import { useDashboardTheme } from '@/lib/useDashboardTheme';
import { useSidebarProvider } from '@/lib/useSidebar';
import { useThemeProvider } from '@/lib/useTheme';

const page = usePage();
useDashboardTheme();
useThemeProvider();
const { collapsed } = useSidebarProvider();
// Menus, tabs and dialogs read the direction from here, so they open on the correct side.
const dir = computed(() => page.props.dir as 'rtl' | 'ltr');
</script>

<template>
    <ConfigProvider :dir="dir">
        <div class="min-h-screen bg-canvas text-ink">
            <AppSidebar />
            <!-- The page moves over as the sidebar folds; under `lg` the sidebar is a drawer. -->
            <div class="flex min-h-screen flex-col transition-[padding] duration-200 ease-out" :class="collapsed ? 'lg:ps-sidebar-collapsed' : 'lg:ps-sidebar'">
                <AppHeader />
                <SectionNav />
                <main id="content" class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col px-4 pt-4 pb-6 md:px-6 lg:px-8">
                    <slot />
                </main>
                <AppFooter />
            </div>
            <Toasts />
            <ConfirmHost />
        </div>
    </ConfigProvider>
</template>
