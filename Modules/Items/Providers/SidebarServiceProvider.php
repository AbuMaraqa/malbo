<?php

namespace Modules\Items\Providers;

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
            (new SidebarItem('Orders List' , 'orders', ['Order View'] , 'orders.index', 1))
        );

        $sidebar->add(
            (new SidebarGroup('Products', 'package', ['Product View']))
                ->add(new SidebarItem('Products List' , 'package', ['Product View'] , 'products.index'))
                ->add(new SidebarItem('Add Product' , 'package', ['Product Create'] , 'products.add'))
                ->add(new SidebarItem('Categories List' , 'package', ['Category View'] , 'categories.index'))
                ->add(new SidebarItem('Attributes List' , 'package', ['Attribute View'] , 'attributes.index'))
                ->add(new SidebarItem('Tags List' , 'package', ['Tag View'] , 'tags.index'))
                ->add(new SidebarItem('Labels List' , 'package', ['Label View'] , 'labels.index'))
                ->add(new SidebarItem('Brands List' , 'package', ['Brand View'] , 'brands.index'))
                ->add(new SidebarItem('Offers List' , 'package', ['Offer View'] , 'offers.index'))
                ->add(new SidebarItem('Addons List' , 'package', ['Addon View'] , 'addons.index'))
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
