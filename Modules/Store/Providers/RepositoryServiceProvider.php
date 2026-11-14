<?php

namespace Modules\Store\Providers;

use Modules\Store\Repositories\CampaignPageRepository;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;
use Modules\Store\Repositories\Contracts\InstagramPostRepositoryInterface;
use Modules\Store\Repositories\InstagramPostRepository;

use Illuminate\Support\ServiceProvider;
use Modules\Store\Repositories\MemberRepository;
use Modules\Store\Repositories\Contracts\MemberRepositoryInterface;
use Modules\Store\Repositories\ContactMessageRepository;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Repositories\PageRepository;
use Modules\Store\Repositories\CartRepository;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Repositories\ShippingZoneRepository;
use Modules\Store\Repositories\AddressRepository;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Store\Repositories\Contracts\WishlistRepositoryInterface;
use Modules\Store\Repositories\WishlistRepository;
use Modules\Store\Repositories\Contracts\MarketingOptOutRepositoryInterface;
use Modules\Store\Repositories\MarketingOptOutRepository;
use Modules\Store\Repositories\Contracts\StockAlertRepositoryInterface;
use Modules\Store\Repositories\StockAlertRepository;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;
use Modules\Store\Repositories\ReviewRepository;

/**
 * Repository bindings of the Store module (`php artisan module:make-entity` adds to this list).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        PageRepositoryInterface::class => PageRepository::class,
        ContactMessageRepositoryInterface::class => ContactMessageRepository::class,
        MemberRepositoryInterface::class => MemberRepository::class,
        ShippingZoneRepositoryInterface::class => ShippingZoneRepository::class,
        CartRepositoryInterface::class => CartRepository::class,
        AddressRepositoryInterface::class => AddressRepository::class,
        WishlistRepositoryInterface::class => WishlistRepository::class,
        MarketingOptOutRepositoryInterface::class => MarketingOptOutRepository::class,
        StockAlertRepositoryInterface::class => StockAlertRepository::class,
        ReviewRepositoryInterface::class => ReviewRepository::class,
        InstagramPostRepositoryInterface::class => InstagramPostRepository::class,
        CampaignPageRepositoryInterface::class => CampaignPageRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
