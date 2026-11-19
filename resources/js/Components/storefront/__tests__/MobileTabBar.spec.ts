import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import { page } from '@/test/setup';

import MobileTabBar from '../MobileTabBar.vue';
import type { StoreLayoutProps } from '../types';

function store(signedIn: boolean, wishlist: number[] = []): StoreLayoutProps {
    return {
        categories_url: 'http://localhost/ar/categories',
        wishlist,
        account: {
            signed_in: signedIn,
            login_url: 'http://localhost/ar/login',
            account_url: 'http://localhost/ar/account',
            wishlist_url: 'http://localhost/ar/account/wishlist',
        },
    } as unknown as StoreLayoutProps;
}

function tab(wrapper: ReturnType<typeof mount>, key: string) {
    return wrapper.find(`[data-tab="${key}"]`);
}

describe('MobileTabBar', () => {
    afterEach(() => {
        page.component = 'Store/Storefront/Shop';
    });

    it('sends a guest to sign in for the wishlist and the account', () => {
        const wrapper = mount(MobileTabBar, { props: { store: store(false) } });

        expect(tab(wrapper, 'home').attributes('href')).toBe('http://localhost/ar');
        expect(tab(wrapper, 'categories').attributes('href')).toBe('http://localhost/ar/categories');
        expect(tab(wrapper, 'wishlist').attributes('href')).toBe('http://localhost/ar/login');
        expect(tab(wrapper, 'account').attributes('href')).toBe('http://localhost/ar/login');
    });

    it('marks the current page, the wishlist apart from the rest of the account', () => {
        page.component = 'Store/Storefront/Account/Wishlist';
        const wrapper = mount(MobileTabBar, { props: { store: store(true, [4, 9]) } });

        expect(tab(wrapper, 'wishlist').attributes('aria-current')).toBe('page');
        expect(tab(wrapper, 'account').attributes('aria-current')).toBeUndefined();
        expect(tab(wrapper, 'wishlist').attributes('href')).toBe('http://localhost/ar/account/wishlist');
        expect(tab(wrapper, 'wishlist').text()).toContain('2');
    });

    it('counts the shop and a category as the categories tab', () => {
        page.component = 'Store/Storefront/Category';
        const wrapper = mount(MobileTabBar, { props: { store: store(true) } });

        expect(tab(wrapper, 'categories').attributes('aria-current')).toBe('page');
        expect(tab(wrapper, 'home').attributes('aria-current')).toBeUndefined();
    });
});
