<?php

namespace Modules\GeoLocation\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\GeoLocation\Repositories\CityRepository;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\GovernorateRepositoryInterface;
use Modules\GeoLocation\Repositories\CountryRepository;
use Modules\GeoLocation\Repositories\DistrictRepository;
use Modules\GeoLocation\Repositories\GovernorateRepository;

/**
 * Repository bindings of the GeoLocation module.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        CountryRepositoryInterface::class => CountryRepository::class,
        GovernorateRepositoryInterface::class => GovernorateRepository::class,
        CityRepositoryInterface::class => CityRepository::class,
        DistrictRepositoryInterface::class => DistrictRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
