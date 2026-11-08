import type { Component } from 'vue';

import BoxIcon from '~icons/solar/box-bold-duotone';
import OrdersIcon from '~icons/solar/bag-bold-duotone';
import BranchesIcon from '~icons/solar/shop-2-bold-duotone';
import HomeIcon from '~icons/solar/home-2-bold-duotone';
import SettingsIcon from '~icons/solar/settings-bold-duotone';
import StoreIcon from '~icons/solar/shop-bold-duotone';
import UsersIcon from '~icons/solar/users-group-rounded-bold-duotone';

/** Sidebar providers name their icon with one of these keys. Duotone, as in the old panel. */
export const navIcons: Record<string, Component> = {
    home: HomeIcon,
    users: UsersIcon,
    settings: SettingsIcon,
    package: BoxIcon,
    orders: OrdersIcon,
    store: StoreIcon,
    branches: BranchesIcon,
};

export const fallbackNavIcon = BoxIcon;

export interface NavEntry {
    type: 'item' | 'group';
    title: string;
    icon: string;
    route?: string;
    children?: NavEntry[];
}
