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
            (new SidebarItem('Orders List' , 'orders', ['Order View'] , 'orders.index', 1))->section('Sales')
        );

        // Next to the orders: returns and exchanges of delivered orders.
        $sidebar->add((new SidebarItem('Return requests', 'returns', ['Return Request View List'], 'return-requests.index', 2))->section('Sales'));

        // Orders on their way: courier, tracking number, status.
        $sidebar->add((new SidebarItem('Shipments', 'shipments', ['Shipment View List'], 'shipments.index', 3))->section('Sales'));

        // The shared stock: its ledger, what runs low, goods that arrive and corrections.
        $sidebar->add(
            (new SidebarGroup('Inventory', 'inventory', [], 21))->section('Catalog')
                ->add(new SidebarItem('Stock movements', 'inventory', ['Stock View List'], 'stock.movements'))
                ->add(new SidebarItem('Low stock', 'inventory', ['Stock View List'], 'stock.low'))
                ->add(new SidebarItem('Receive goods', 'inventory', ['Stock Update'], 'stock.receive'))
                ->add(new SidebarItem('Stock adjustment', 'inventory', ['Stock Update'], 'stock.adjust'))
        );

        $sidebar->add(
            (new SidebarGroup('Products', 'package', ['Product View'], 20))->section('Catalog')
                ->add(new SidebarItem('Products List' , 'package', ['Product View'] , 'products.index'))
                ->add(new SidebarItem('Add Product' , 'package', ['Product Create'] , 'products.add'))
                ->add(new SidebarItem('Sets and bundles', 'package', ['Bundle View List'], 'bundles.index'))
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
