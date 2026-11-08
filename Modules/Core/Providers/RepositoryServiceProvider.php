<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Repositories\Contracts\CurrencyRepositoryInterface;
use Modules\Core\Repositories\Contracts\DashboardRepositoryInterface;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Repositories\Contracts\SettingRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Repositories\CurrencyRepository;
use Modules\Core\Repositories\DashboardRepository;
use Modules\Core\Repositories\RoleRepository;
use Modules\Core\Repositories\SettingRepository;
use Modules\Core\Repositories\UserRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CurrencyRepositoryInterface::class => CurrencyRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,
    ];
}
