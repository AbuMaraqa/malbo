<?php

namespace Modules\Items\Support;

use Illuminate\Container\Attributes\Singleton;

/**
 * Links other modules add to the row menu of the admin's product list (the wholesale prices of a
 * product, for one). Items knows only their label, route name and permission: the route takes the
 * product's id, and the link shows to those who have the permission.
 */
#[Singleton]
final class ProductRowLinks
{
    /** @var list<array{label: string, route: string, permission: string}> */
    private array $links = [];

    /**
     * @param  string  $label  an English key, translated when the page is sent
     */
    public function add(string $label, string $route, string $permission): void
    {
        $this->links[] = ['label' => $label, 'route' => $route, 'permission' => $permission];
    }

    /**
     * @return list<array{label: string, route: string, permission: string}>
     */
    public function all(): array
    {
        return array_map(fn (array $link) => [...$link, 'label' => __($link['label'])], $this->links);
    }
}
