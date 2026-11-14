<?php

namespace Modules\Wholesale\Support;

/**
 * The portal's own links (catalog, cart, orders, PDF catalog, statement), shown above its pages.
 */
final class PortalNav
{
    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public static function links(): array
    {
        return [
            ['key' => 'catalog', 'label' => __('Wholesale catalog'), 'url' => route('store.wholesale.catalog')],
            ['key' => 'cart', 'label' => __('Wholesale cart'), 'url' => route('store.wholesale.cart')],
            ['key' => 'orders', 'label' => __('My wholesale orders'), 'url' => route('store.wholesale.orders')],
            ['key' => 'catalog-pdf', 'label' => __('PDF catalog'), 'url' => route('store.wholesale.catalog-pdf')],
            ['key' => 'statement', 'label' => __('Statement of account'), 'url' => route('store.wholesale.statement')],
            ['key' => 'account', 'label' => __('Wholesale account'), 'url' => route('store.wholesale.status')],
        ];
    }
}
