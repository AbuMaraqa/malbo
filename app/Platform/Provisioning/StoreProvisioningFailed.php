<?php

declare(strict_types=1);

namespace App\Platform\Provisioning;

use RuntimeException;

/**
 * Creating a store failed and everything it had created was removed. The message is
 * safe to show; the cause is the previous exception.
 */
class StoreProvisioningFailed extends RuntimeException {}
