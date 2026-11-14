<?php

namespace Modules\Items\Services;

use Illuminate\Validation\ValidationException;
use Modules\Items\Contracts\OnAccountPolicy;
use Modules\Items\Entities\Order;

/**
 * The store alone sells nothing on account.
 */
class RefuseOnAccount implements OnAccountPolicy
{
    public function assertAllowed(?Order $order, float $increase): void
    {
        throw ValidationException::withMessages(['payment_method' => __('Only a wholesale order of a merchant with credit can be on account.')]);
    }
}
