<?php

namespace Modules\Branch\Providers;

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
            (new SidebarGroup('Branches', 'branches', [], 80)) // no permissions: shown when one of its items is
                ->add(new SidebarItem('Branches List', 'branches', ['Branch View List'], 'branches.index'))
                ->add(new SidebarItem('Add Branch', 'branches', ['Branch Create'], 'branches.add'))
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
