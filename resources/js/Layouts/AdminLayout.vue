<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ConfigProvider } from 'reka-ui';
import { computed } from 'vue';

import AppHeader from '@/Components/layout/AppHeader.vue';
import AppSidebar from '@/Components/layout/AppSidebar.vue';
import SidebarContext from '@/Components/layout/SidebarContext.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import Toasts from '@/Components/ui/Toasts.vue';
import { useDashboardTheme } from '@/lib/useDashboardTheme';
import ThemeProvider from '@/tailadmin/layout/ThemeProvider.vue';
import { SidebarInset, SidebarProvider } from '@/tailadmin/ui/sidebar';

const page = usePage();
useDashboardTheme();
// Menus, tabs and dialogs read the direction from here, so they open on the correct side.
const dir = computed(() => page.props.dir as 'rtl' | 'ltr');
</script>

<template>
    <ConfigProvider :dir="dir">
        <ThemeProvider>
            <SidebarContext>
                <SidebarProvider>
                    <AppSidebar />
                    <SidebarInset>
                        <AppHeader />
                        <div class="mx-auto flex w-full max-w-(--breakpoint-2xl) flex-1 flex-col p-4 md:p-6">
                            <slot />
                        </div>
                    </SidebarInset>
                    <Toasts />
                    <ConfirmHost />
                </SidebarProvider>
            </SidebarContext>
        </ThemeProvider>
    </ConfigProvider>
</template>
