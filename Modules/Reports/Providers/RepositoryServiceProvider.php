<?php

namespace Modules\Reports\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Reports\Repositories\CartReportRepository;
use Modules\Reports\Repositories\Contracts\CartReportRepositoryInterface;
use Modules\Reports\Repositories\Contracts\MerchantReportRepositoryInterface;
use Modules\Reports\Repositories\Contracts\SalesReportRepositoryInterface;
use Modules\Reports\Repositories\MerchantReportRepository;
use Modules\Reports\Repositories\SalesReportRepository;

/**
 * The reports' read models: aggregate SQL only, nothing is written.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        SalesReportRepositoryInterface::class => SalesReportRepository::class,
        MerchantReportRepositoryInterface::class => MerchantReportRepository::class,
        CartReportRepositoryInterface::class => CartReportRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $contract => $repository) {
            $this->app->bind($contract, $repository);
        }
    }
}
