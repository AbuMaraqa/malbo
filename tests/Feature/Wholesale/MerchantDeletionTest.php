<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Services\MerchantLedger;

/**
 * A merchant's account is a financial record: deleting its user, or the merchant, never takes the
 * ledger with it. The user's delete is refused with the usual "linked to other records" message.
 */
class MerchantDeletionTest extends WholesaleTestCase
{
    public function test_a_merchant_with_ledger_entries_cannot_be_deleted(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
        $merchant = $this->merchant();
        app(MerchantLedger::class)->recordAdjustment($merchant, ['amount' => 100, 'entry_date' => now()->toDateString(), 'note' => 'رصيد'], $admin->id);

        $this->actingAs($admin)->from($this->siteUrl('/ar/admin/users'))->delete($this->siteUrl('/ar/admin/users/'.$merchant->user_id))
            ->assertRedirect()
            ->assertSessionHas('error', __('This user is linked to other records and cannot be deleted.'));

        $this->assertNotNull(User::query()->find($merchant->user_id));
        $this->assertNotNull(Merchant::query()->find($merchant->id));
        $this->assertSame(1, LedgerEntry::query()->where('merchant_id', $merchant->id)->count());
    }

    public function test_the_merchant_row_cannot_take_its_ledger_with_it(): void
    {
        $merchant = $this->merchant();
        app(MerchantLedger::class)->recordAdjustment($merchant, ['amount' => 5, 'entry_date' => now()->toDateString(), 'note' => 'x'], $this->staff([])->id);

        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => Merchant::query()->whereKey($merchant->id)->delete());
            $this->fail('The merchant was deleted with its ledger.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('23503', $e->getCode());
        }

        $this->assertSame(1, LedgerEntry::query()->where('merchant_id', $merchant->id)->count());
    }
}
