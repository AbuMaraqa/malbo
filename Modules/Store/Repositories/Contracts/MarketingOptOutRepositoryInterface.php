<?php

namespace Modules\Store\Repositories\Contracts;

use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface MarketingOptOutRepositoryInterface extends RepositoryInterface
{
    /**
     * Whether either contact (already normalised) opted out.
     */
    public function optedOut(?string $email, ?string $phoneDigits): bool;
}
