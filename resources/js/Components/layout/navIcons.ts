import type { Component } from 'vue';

import ActivityIcon from '~icons/solar/history-bold-duotone';
import BoxIcon from '~icons/solar/box-bold-duotone';
import OrdersIcon from '~icons/solar/bag-bold-duotone';
import BranchesIcon from '~icons/solar/shop-2-bold-duotone';
import HomeIcon from '~icons/solar/home-2-bold-duotone';
import InventoryIcon from '~icons/solar/box-minimalistic-bold-duotone';
import MarketingIcon from '~icons/solar/graph-new-up-bold-duotone';
import ReportsIcon from '~icons/solar/chart-2-bold-duotone';
import ReturnsIcon from '~icons/solar/restart-bold-duotone';
import SettingsIcon from '~icons/solar/settings-bold-duotone';
import ShipmentsIcon from '~icons/solar/delivery-bold-duotone';
import StoreIcon from '~icons/solar/shop-bold-duotone';
import UsersIcon from '~icons/solar/users-group-rounded-bold-duotone';
import WholesaleIcon from '~icons/solar/case-round-bold-duotone';

/** Sidebar providers name their icon with one of these keys. Duotone, as in the old panel. */
export const navIcons: Record<string, Component> = {
    home: HomeIcon,
    users: UsersIcon,
    settings: SettingsIcon,
    package: BoxIcon,
    orders: OrdersIcon,
    returns: ReturnsIcon,
    shipments: ShipmentsIcon,
    inventory: InventoryIcon,
    activity: ActivityIcon,
    store: StoreIcon,
    branches: BranchesIcon,
    wholesale: WholesaleIcon,
    marketing: MarketingIcon,
    reports: ReportsIcon,
};

export const fallbackNavIcon = BoxIcon;

export interface NavEntry {
    type: 'item' | 'group';
    title: string;
    icon: string;
    route?: string;
    children?: NavEntry[];
    /** The heading the entry sits under ("Sales"); entries of one section come together. */
    section?: string | null;
}
