<?php

namespace Modules\Wholesale\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Wholesale\Repositories\CatalogShareRepository;
use Modules\Wholesale\Repositories\Contracts\CatalogShareRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Repositories\LedgerEntryRepository;
use Modules\Wholesale\Repositories\Contracts\PreorderRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleCartRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleOrderRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesalePriceRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Repositories\MerchantRepository;
use Modules\Wholesale\Repositories\PreorderRepository;
use Modules\Wholesale\Repositories\TierRepository;
use Modules\Wholesale\Repositories\WholesaleCartRepository;
use Modules\Wholesale\Repositories\WholesaleOrderRepository;
use Modules\Wholesale\Repositories\WholesalePriceRepository;
use Modules\Wholesale\Repositories\WholesaleProductRepository;

/**
 * Repository bindings of the Wholesale module.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        MerchantRepositoryInterface::class => MerchantRepository::class,
        TierRepositoryInterface::class => TierRepository::class,
        WholesalePriceRepositoryInterface::class => WholesalePriceRepository::class,
        WholesaleProductRepositoryInterface::class => WholesaleProductRepository::class,
        WholesaleCartRepositoryInterface::class => WholesaleCartRepository::class,
        WholesaleOrderRepositoryInterface::class => WholesaleOrderRepository::class,
        PreorderRepositoryInterface::class => PreorderRepository::class,
        CatalogShareRepositoryInterface::class => CatalogShareRepository::class,
        LedgerEntryRepositoryInterface::class => LedgerEntryRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
