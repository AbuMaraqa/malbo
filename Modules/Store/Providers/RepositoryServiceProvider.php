<?php

namespace Modules\Store\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Store\Repositories\MemberRepository;
use Modules\Store\Repositories\Contracts\MemberRepositoryInterface;
use Modules\Store\Repositories\ContactMessageRepository;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Repositories\PageRepository;

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
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
