<?php

namespace Modules\Reports\Providers;

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

        // After the orders, returns, shipments and wholesale: what they add up to.
        $sidebar->add(
            (new SidebarGroup('Reports', 'reports', [], 5))->section('Sales')
                ->add(new SidebarItem('Sales report', 'reports', ['Sales Report View'], 'reports.sales.index'))
                ->add(new SidebarItem('Store and wholesale', 'reports', ['Channel Report View'], 'reports.channels.index'))
                ->add(new SidebarItem('Sales by product', 'reports', ['Product Report View'], 'reports.products.index'))
                ->add(new SidebarItem('Best and slow sellers', 'reports', ['Seller Report View'], 'reports.sellers.index'))
                ->add(new SidebarItem('Return rate', 'reports', ['Return Report View'], 'reports.returns.index'))
                ->add(new SidebarItem('Merchant performance', 'reports', ['Merchant Report View'], 'reports.merchants.index'))
                ->add(new SidebarItem('Abandoned carts report', 'reports', ['Cart Report View'], 'reports.carts.index'))
                ->add(new SidebarItem('Conversion and order value', 'reports', ['Conversion Report View'], 'reports.conversion.index'))
        );
    }

    public function provides(): array
    {
        return [];
    }
}
