<?php

namespace Modules\Items\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Items\Repositories\AddonRepository;
use Modules\Items\Repositories\AttributeRepository;
use Modules\Items\Repositories\BrandRepository;
use Modules\Items\Repositories\CategoryRepository;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;
use Modules\Items\Repositories\Contracts\BrandRepositoryInterface;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\LabelRepositoryInterface;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Repositories\Contracts\TagRepositoryInterface;
use Modules\Items\Repositories\LabelRepository;
use Modules\Items\Repositories\OfferRepository;
use Modules\Items\Repositories\OrderRepository;
use Modules\Items\Repositories\ProductRepository;
use Modules\Items\Repositories\TagRepository;

/**
 * Repository bindings of the Items module (`php artisan module:make-entity` adds to this list).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        TagRepositoryInterface::class => TagRepository::class,
        LabelRepositoryInterface::class => LabelRepository::class,
        BrandRepositoryInterface::class => BrandRepository::class,
        AttributeRepositoryInterface::class => AttributeRepository::class,
        AddonRepositoryInterface::class => AddonRepository::class,
        OfferRepositoryInterface::class => OfferRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
