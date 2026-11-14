<?php

namespace Modules\Store\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Store\Services\Demo\DemoStoreBuilder;

/**
 * Fills a development or staging site with a men's fashion demo store (catalog in ar/en/he, sets,
 * shipping zones, a coupon, customers, merchants, orders at every stage, reviews, a campaign page).
 * Opt-in: never part of `db:seed`, never in production. Run twice, it does nothing the second time.
 */
class BuildDemoStore extends Command
{
    protected $signature = 'malbo:demo {--no-images : Skip the placeholder pictures}';

    protected $description = 'Build the MALBO demo store (not for production)';

    public function handle(DemoStoreBuilder $builder): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('The demo store is never built in production.');

            return self::FAILURE;
        }

        if ($builder->exists()) {
            $this->info('The demo store is already here: nothing to do.');

            return self::SUCCESS;
        }

        // Demo orders and accounts write to demo addresses only; still, nothing leaves the machine.
        config(['mail.default' => 'array']);
        Mail::forgetMailers();

        $this->info('Building the demo store...');
        $demo = $builder->build(images: ! $this->option('no-images'));

        $this->table(['What', 'Count'], [
            ['Categories', count($demo->categories)],
            ['Products', count($demo->products)],
            ['Sellable items (variants)', count($demo->items)],
            ['Sets and bundles', count($demo->bundles)],
            ['Shipping zones', count($demo->zones)],
            ['Orders', count($demo->orders) + count($demo->extra['wholesale_orders'] ?? [])],
        ]);

        $this->info("Coupon: {$demo->coupon}");
        $this->table(['Login', 'E-mail', 'Password'], $demo->logins);

        foreach ($demo->notes as $note) {
            $this->warn($note);
        }

        return self::SUCCESS;
    }
}
