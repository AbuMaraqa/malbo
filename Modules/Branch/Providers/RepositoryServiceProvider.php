<?php

namespace Modules\Branch\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Branch\Repositories\BranchRepository;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;

/**
 * Repository bindings of the Branch module (`php artisan module:make-entity` adds to this list).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        BranchRepositoryInterface::class => BranchRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
