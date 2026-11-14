<?php

namespace Modules\Wholesale\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;

class SidebarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SidebarService::class, function () {
            return new SidebarService;
        });
    }

    public function boot(): void
    {
        $sidebar = $this->app->make(SidebarService::class);

        // Merchants, their price tiers and the products' wholesale prices.
        $sidebar->add(
            (new SidebarGroup('Wholesale', 'wholesale', [], 4))->section('Sales')
                ->add(new SidebarItem('Merchants', 'wholesale', ['Merchant View List'], 'merchants.index'))
                ->add(new SidebarItem('Price tiers', 'wholesale', ['Wholesale Tier View List'], 'wholesale-tiers.index'))
                ->add(new SidebarItem('Wholesale prices', 'wholesale', ['Wholesale Price View List'], 'wholesale-prices.index'))
                ->add(new SidebarItem('Pre-orders', 'wholesale', ['Wholesale Preorder View List'], 'wholesale-preorders.index'))
        );
    }

    public function provides(): array
    {
        return [];
    }
}
