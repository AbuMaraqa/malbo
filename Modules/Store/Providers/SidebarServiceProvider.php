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
            (new SidebarGroup('Online store', 'store', [], 90)) // no permissions: shown when one of its items is
                ->add(new SidebarItem('Store appearance' , 'store', ['Appearance Update'] , 'appearance.index'))
                ->add(new SidebarItem('Store pages' , 'store', ['Page View List'] , 'pages.index'))
                ->add(new SidebarItem('Customer messages' , 'store', ['Contact Message View List'] , 'contact-messages.index'))
                ->add(new SidebarItem('Members' , 'store', ['Member View List'] , 'members.index'))
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
