<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Settings, UserCircle } from '@/lib/icons';
import { computed, ref } from 'vue';

import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/tailadmin/ui/dropdown-menu';

const page = usePage();
const isOpen = ref(false);
const user = computed(() => (page.props.auth as { user: { name: string; email: string; avatar: string } | null }).user);
</script>

<template>
    <DropdownMenu v-if="user" :open="isOpen" @update:open="isOpen = $event">
        <DropdownMenuTrigger class="flex items-center text-gray-700 dark:text-gray-400">
            <span class="me-3 size-11 overflow-hidden rounded-lg"><img width="44" height="44" :src="user.avatar" alt="" class="size-full object-cover" /></span>
            <span class="me-1 hidden text-sm font-medium sm:block">{{ user.name }}</span>
            <ChevronDown class="size-5 text-gray-500 duration-200 dark:text-gray-400" :class="{ 'rotate-180': isOpen }" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="12" class="w-65 p-3">
            <div class="px-3 py-2">
                <span class="block truncate text-sm font-medium text-gray-700 dark:text-gray-400">{{ user.name }}</span>
                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ user.email }}</span>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem :to="route('profile.index')"><UserCircle class="size-6" />{{ t('Profile') }}</DropdownMenuItem>
            <DropdownMenuItem v-if="can('Setting View List')" :to="route('settings')"><Settings class="size-6" />{{ t('System Settings') }}</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" @click="router.post('/logout')"><LogOut class="rtl:rotate-180" />{{ t('Sign Out') }}</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
