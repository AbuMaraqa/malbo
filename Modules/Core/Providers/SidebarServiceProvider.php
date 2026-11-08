<?php

namespace Modules\Core\Providers;

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
            (new SidebarItem('Home', 'home', [] ,  'dashboard' , 0))
        );

        $sidebar->add(
            (new SidebarGroup('Users', 'users' , [] , 30))
            ->add(new SidebarItem('Users List' , 'users' , ['User View List'] ,  'users.index' ))
                ->add(new SidebarItem('Add User' , 'users' , ['User Create'] , 'users.add' ))
        );

        $sidebar->add(
            (new SidebarGroup('Settings', 'settings' , [] , 140))
            ->add(new SidebarItem('System Settings', 'settings', ['Setting View List'] , 'settings' ))
            ->add(new SidebarItem('Roles And Permissions', 'settings', ['Roles And Permissions View List'] , 'roles.index' ))
            ->add(new SidebarItem('Currencies', 'settings', ['Currency View List'] ,  'currencies.index' ))
            ->add(new SidebarItem('Countries', 'settings', ['Country View List'] , 'countries.index' ))
            ->add(new SidebarItem('Governorates', 'settings', ['Governorate View List'] , 'governorates.index' ))
            ->add(new SidebarItem('Cities', 'settings', ['City View List'] , 'cities.index' ))
            ->add(new SidebarItem('Districts', 'settings', ['District View List'] , 'districts.index' ))
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
