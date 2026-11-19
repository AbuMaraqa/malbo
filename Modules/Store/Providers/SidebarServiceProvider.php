<?php

namespace Modules\Store\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;

class SidebarServiceProvider extends ServiceProvider
{
    /**
     * Registration the service provider.
     */
    public function register(): void {
        $this->app->singleton(SidebarService::class, function () {
            return new SidebarService();
        });
    }

    public function boot(): void {
        $sidebar = $this->app->make(SidebarService::class);

        $sidebar->add(
            (new SidebarGroup('Online store', 'store', [], 90))->section('Store') // no permissions: shown when one of its items is
                ->add(new SidebarItem('Store appearance' , 'store', ['Appearance Update'] , 'appearance.index'))
                ->add(new SidebarItem('Home slider' , 'store', ['Hero Slide View List'] , 'hero-slides.index'))
                ->add(new SidebarItem('Store pages' , 'store', ['Page View List'] , 'pages.index'))
                ->add(new SidebarItem('Customer messages' , 'store', ['Contact Message View List'] , 'contact-messages.index'))
                ->add(new SidebarItem('Members' , 'store', ['Member View List'] , 'members.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Marketing', 'marketing', [], 95))->section('Store') // shown when one of its items is
                ->add(new SidebarItem('Reviews', 'marketing', ['Review View List'], 'reviews.index'))
                ->add(new SidebarItem('Abandoned carts', 'marketing', ['Abandoned Cart View List'], 'abandoned-carts.index'))
                ->add(new SidebarItem('Stock alerts', 'marketing', ['Stock Alert View List'], 'stock-alerts.index'))
                ->add(new SidebarItem('Campaign pages', 'marketing', ['Campaign Page View List'], 'campaign-pages.index'))
                ->add(new SidebarItem('Instagram', 'marketing', ['Instagram Post View List'], 'instagram-posts.index'))
                ->add(new SidebarItem('Marketing settings', 'marketing', ['Marketing Settings Update'], 'marketing.index'))
        );

        // Delivery is a store setting: it joins Core's Settings group once every module has booted.
        $this->app->booted(function () use ($sidebar): void {
            $item = new SidebarItem('Shipping zones', 'settings', ['Shipping Zone View List'], 'shipping-zones.index');
            $settings = $sidebar->group('Settings');

            $settings ? $settings->add($item) : $sidebar->add((new SidebarGroup('Settings', 'settings', [], 140))->section('Administration')->add($item));
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
