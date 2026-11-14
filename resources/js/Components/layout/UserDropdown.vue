<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { DropdownMenuItem, DropdownMenuPortal, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from 'reka-ui';
import { computed } from 'vue';

import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownMenu from '@/Components/ui/DropdownMenu.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import Switch from '@/Components/ui/Switch.vue';
import { buttonClasses } from '@/lib/buttonClasses';
import { can, t } from '@/lib/i18n';
import { Check, ChevronRight, LogOut, Moon, Settings, Translation, UserCircle } from '@/lib/icons';
import { route } from '@/lib/route';
import { useLocales } from '@/lib/useLocales';
import { useTheme } from '@/lib/useTheme';

const page = usePage();
const user = computed(() => (page.props.auth as { user: { name: string; email: string; avatar: string } | null }).user);
const { locales, current, urlFor } = useLocales();
const { isDarkMode, toggleTheme } = useTheme();

const row = 'flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium text-gray-700 outline-none select-none data-highlighted:bg-gray-100 data-highlighted:text-gray-900 data-[state=open]:bg-gray-100 dark:text-gray-300 dark:data-highlighted:bg-white/5 dark:data-highlighted:text-white dark:data-[state=open]:bg-white/5 [&>svg:first-child]:size-[18px] [&>svg:first-child]:text-gray-500 dark:[&>svg:first-child]:text-gray-400';
</script>

<template>
    <DropdownMenu v-if="user" content-class="w-72">
        <template #trigger>
            <button type="button" class="flex items-center gap-2.5 rounded-full outline-none focus-visible:shadow-focus-ring" :aria-label="t('Account menu')">
                <img width="36" height="36" :src="user.avatar" alt="" class="size-9 rounded-full object-cover ring-2 ring-success-500/80 ring-offset-2 ring-offset-card" />
            </button>
        </template>

        <div class="flex items-center gap-3 px-2.5 py-2">
            <img width="40" height="40" :src="user.avatar" alt="" class="size-10 shrink-0 rounded-full object-cover" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ user.name }}</p>
                <p class="truncate text-xs text-muted" dir="ltr">{{ user.email }}</p>
            </div>
        </div>

        <DropdownSeparator />

        <DropdownItem :href="route('profile.index')"><UserCircle />{{ t('Profile') }}</DropdownItem>
        <DropdownItem v-if="can('Setting View List')" :href="route('settings')"><Settings />{{ t('System Settings') }}</DropdownItem>

        <DropdownMenuSub v-if="locales.length > 1">
            <DropdownMenuSubTrigger :class="row">
                <Translation />
                <span class="flex-1">{{ t('Language') }}</span>
                <span class="flex items-center gap-1.5 rounded-md border border-line px-1.5 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                    {{ current?.native }}
                    <img v-if="current" :src="`/images/flags/${current.flag}.svg`" alt="" class="size-3.5 rounded-full object-cover" />
                </span>
                <ChevronRight class="size-4 text-gray-400 rtl:rotate-180" />
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent :side-offset="8" :collision-padding="12" class="z-[71] w-52 rounded-xl border border-line bg-card p-2 text-sm shadow-theme-lg data-[state=open]:animate-pop-in">
                    <DropdownMenuItem v-for="locale in locales" :key="locale.code" as-child>
                        <a :href="urlFor(locale.code)" :hreflang="locale.code" :lang="locale.code" :dir="locale.dir" :class="row" :aria-current="locale.code === current?.code ? 'true' : undefined">
                            <img :src="`/images/flags/${locale.flag}.svg`" alt="" class="size-4.5 shrink-0 rounded-full object-cover" />
                            <span class="flex-1 truncate">{{ locale.native }}</span>
                            <Check v-if="locale.code === current?.code" class="size-4 text-brand-500" />
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuPortal>
        </DropdownMenuSub>

        <DropdownSeparator />

        <!-- Stays open: the switch shows the change at once. -->
        <DropdownMenuItem :class="row" @select.prevent="toggleTheme">
            <Moon />
            <span class="flex-1">{{ t('Dark mode') }}</span>
            <Switch :model-value="isDarkMode" :aria-label="t('Dark mode')" tabindex="-1" class="pointer-events-none" />
        </DropdownMenuItem>

        <div class="px-0.5 pt-2 pb-0.5">
            <button type="button" :class="buttonClasses({ variant: 'secondary', size: 'md', class: 'w-full' })" @click="router.post('/logout')">
                <LogOut class="rtl:rotate-180" />{{ t('Sign Out') }}
            </button>
        </div>
    </DropdownMenu>
</template>
