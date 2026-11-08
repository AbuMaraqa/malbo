<?php

namespace Tests\Feature\Branch;

use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Tests\TenantTestCase;

class BranchRepositoryTest extends TenantTestCase
{
    public function test_open_branches_for_the_storefront(): void
    {
        $open = Branch::factory()->create(['status' => BranchStatus::OPEN]);
        Branch::factory()->create(['status' => BranchStatus::CLOSED]);

        $this->assertSame([$open->id], app(BranchRepositoryInterface::class)->openForStorefront()->modelKeys());
    }
}
