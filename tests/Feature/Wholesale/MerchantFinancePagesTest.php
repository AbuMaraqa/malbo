<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Services\MerchantLedger;
use Modules\Wholesale\Services\MerchantStatement;

/**
 * The merchant's finance page in the admin (the ledger with a running balance, filters and totals;
 * payments with a receipt, adjustments, the printed statement) and the merchant's statement in the
 * portal (a date range, an opening balance, a PDF).
 */
class MerchantFinancePagesTest extends WholesaleTestCase
{
    private User $admin;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
        $this->merchant = $this->merchant(attributes: ['credit_enabled' => true, 'credit_limit' => 500]);
    }

    public function test_invoiced_orders_with_no_payment_are_flagged(): void
    {
        $belt = $this->single(['stock_quantity' => 10]);
        $order = app(\Modules\Items\Services\OrderService::class)->checkout([
            'user_id' => $this->merchant->user_id, 'customer_name' => 'x', 'contact_phone' => '0599', 'delivery_address' => 'x', 'payment_method' => 1,
        ], [['product_id' => $belt->id, 'quantity' => 2, 'price' => 20]], \Modules\Items\Enums\OrderChannel::B2B);
        foreach ([\Modules\Items\Enums\OrderStatus::CONFIRMED, \Modules\Items\Enums\OrderStatus::PREPARING, \Modules\Items\Enums\OrderStatus::OUT_FOR_DELIVERY, \Modules\Items\Enums\OrderStatus::DELIVERED] as $status) {
            app(\Modules\Items\Services\OrderService::class)->changeStatus($order->fresh(), $status, $this->admin->id);
        }

        $this->actingAs($this->admin)->get($this->url('/ledger'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.type', 'invoice')
            ->where('table.data.0.unpaid', true)
            ->where('totals.unpaid_invoices', 1)
            ->etc());

        $order->forceFill(['payment_status' => \Modules\Items\Enums\PaymentStatus::PAID])->save();

        $this->actingAs($this->admin)->get($this->url('/ledger'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.unpaid', false)
            ->where('totals.unpaid_invoices', 0)
            ->etc());
    }

    public function test_the_ledger_page_shows_running_balance_and_totals(): void
    {
        $this->entries();

        $this->actingAs($this->admin)->get($this->url('/ledger'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Merchant/Ledger')
            ->where('merchant.shop_name', 'محل الأناقة')
            ->where('table.meta.total', 3)
            // Newest first, each with the balance after it.
            ->where('table.data.0.type', 'payment')
            ->where('table.data.0.amount', -40)
            ->where('table.data.0.running_balance', 70)
            ->where('table.data.0.method', 'نقدًا')
            ->where('table.data.2.running_balance', 100)
            ->where('totals.balance', 70)
            ->where('totals.invoiced', 0)
            ->where('totals.paid', 40)
            ->where('totals.adjusted', 110)
            ->where('credit.limit_label', '500.00 ₪')
            ->where('credit.room_label', '430.00 ₪')
            ->has('types', 4)
            ->has('methods', 3)
            ->etc());
    }

    public function test_each_filter(): void
    {
        $this->entries();

        $only = fn (string $query, int $count, int|float|null $runningOfFirst = null) => $this->actingAs($this->admin)->get($this->url('/ledger?'.$query))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', $count)
                ->when($runningOfFirst !== null, fn (Assert $page) => $page->where('table.data.0.running_balance', $runningOfFirst))->etc());

        $only('filter[type]=adjustment', 2, 110);
        $only('filter[method]=cash', 1, 70);
        $only('filter[entry_date_from]=2026-11-02', 2);
        $only('filter[entry_date_to]=2026-11-01', 1, 100);
        $only('filter[search]=TR-77', 1);
    }

    public function test_recording_a_payment_with_a_private_receipt(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/payments'), [
            'amount' => '150.50', 'method' => 'bank_transfer', 'entry_date' => '2026-11-09', 'reference' => 'TR-9', 'note' => 'دفعة',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('-150.50', $entry->amount);
        $this->assertSame('receipt.pdf', $entry->receipt_name);
        Storage::disk('local')->assertExists($entry->receipt_path);
        $this->assertFalse(Storage::disk('public')->exists($entry->receipt_path));

        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/payments'), [
            'amount' => '10', 'method' => 'cash', 'entry_date' => '2026-11-09',
            'receipt' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('receipt');
        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/payments'), ['amount' => '0', 'method' => 'cash', 'entry_date' => '2026-11-09'])->assertSessionHasErrors('amount');
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_an_adjustment_needs_a_note(): void
    {
        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/adjustments'), ['amount' => '-12', 'entry_date' => '2026-11-09', 'note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/adjustments'), ['amount' => '-12', 'entry_date' => '2026-11-09', 'note' => 'خصم'])->assertSessionHasNoErrors();

        $this->assertSame('-12.00', LedgerEntry::query()->sole()->amount);
    }

    public function test_an_entry_cannot_be_dated_in_the_future(): void
    {
        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/payments'), ['amount' => '10', 'method' => 'cash', 'entry_date' => $tomorrow])->assertSessionHasErrors('entry_date');
        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/adjustments'), ['amount' => '10', 'entry_date' => $tomorrow, 'note' => 'x'])->assertSessionHasErrors('entry_date');
        $this->actingAs($this->admin)->from($this->url('/ledger'))->post($this->url('/payments'), ['amount' => '10', 'method' => 'cash', 'entry_date' => now()->toDateString()])->assertSessionHasNoErrors();

        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_the_receipt_needs_a_signed_link(): void
    {
        Storage::fake('local');
        $entry = app(MerchantLedger::class)->recordPayment($this->merchant, ['amount' => 5, 'method' => 'cash', 'entry_date' => '2026-11-09'], UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), $this->admin->id);

        $this->actingAs($this->admin)->get($this->url('/ledger/'.$entry->id.'/receipt'))->assertForbidden();
        $this->actingAs($this->admin)->get(URL::temporarySignedRoute('merchants.receipt', now()->addMinutes(5), ['merchant' => $this->merchant->id, 'entry' => $entry->id]))->assertOk();

        $other = $this->merchant();
        $this->actingAs($this->admin)->get(URL::temporarySignedRoute('merchants.receipt', now()->addMinutes(5), ['merchant' => $other->id, 'entry' => $entry->id]))->assertNotFound();
    }

    public function test_the_admin_statement_pdf(): void
    {
        $this->entries();

        $response = $this->actingAs($this->admin)->get($this->url('/statement?from=2026-11-01&to=2026-11-30'))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_statement_with_opening_balance_and_range(): void
    {
        $this->entries();

        $statement = app(MerchantStatement::class)->for($this->merchant, '2026-11-02', '2026-11-30');

        $this->assertSame(100.0, $statement['opening']);
        $this->assertSame([110.0, 70.0], array_column($statement['rows'], 'balance'));
        $this->assertSame(70.0, $statement['closing']);
        $this->assertSame('10.00 ₪', $statement['rows'][0]['debit_label']);
        $this->assertNull($statement['rows'][0]['credit_label']);
        $this->assertSame('40.00 ₪', $statement['rows'][1]['credit_label']);
    }

    public function test_the_portal_statement(): void
    {
        $this->entries();

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2026-11-02&to=2026-11-30'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Wholesale/Portal/Statement')
                ->where('statement.opening', 100)
                ->has('statement.rows', 2)
                ->where('statement.closing', 70)
                ->where('filters.from', '2026-11-02')
                ->where('pdfUrl', fn (string $url) => str_contains($url, 'from=2026-11-02'))
                ->missing('statement.rows.1.note') // a payment's note is the staff's
                ->etc());

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2026-13-40'))->assertSessionHasErrors('from');
    }

    public function test_the_portal_statement_pdf(): void
    {
        $this->entries();

        $response = $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement/pdf?from=2026-11-01'))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_merchant_sees_only_their_own_statement(): void
    {
        $this->entries();
        $other = $this->merchant();

        $this->actingAs($other->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2026-11-01'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('statement.rows', 0)->where('statement.closing', 0)->etc());

        // Someone with no wholesale profile has no statement.
        $customer = User::forceCreate(['name' => 'C', 'email' => 'c-'.Str::random(8).'@shop.test', 'password' => 'secret-password']);
        $this->flushSession();
        $this->actingAs($customer)->get($this->siteUrl('/ar/wholesale/account/statement'))->assertRedirect(route('store.wholesale.status'));
    }

    public function test_a_suspended_merchant_still_sees_its_statement(): void
    {
        $this->entries();
        $this->merchant->forceFill(['status' => MerchantStatus::SUSPENDED])->save();

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2026-11-01'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('statement.rows', 3)->where('statement.closing', 70)->etc());
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement/pdf?from=2026-11-01'))->assertOk()->getContent());

        // The prices stay closed to it.
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale'))->assertRedirect(route('store.wholesale.status'));
    }

    public function test_a_statement_covers_at_most_two_years(): void
    {
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2024-01-01&to=2026-11-01'))->assertSessionHasErrors('from');
        $this->actingAs($this->admin)->get($this->url('/statement?from=2024-01-01&to=2026-11-01'))->assertSessionHasErrors('from');
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement?from=2024-11-02&to=2026-11-01'))->assertOk();
    }

    public function test_the_statement_pdf_is_rate_limited(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/account/statement/pdf'))->assertOk();
        }

        $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/account/statement'))
            ->get($this->siteUrl('/ar/wholesale/account/statement/pdf'))->assertRedirect()->assertSessionHas('error');
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Merchant View List');

        $this->actingAs($viewer)->get($this->url('/ledger'))->assertOk();
        $this->actingAs($viewer)->get($this->url('/statement'))->assertOk();
        $this->actingAs($viewer)->post($this->url('/payments'), ['amount' => 5, 'method' => 'cash', 'entry_date' => '2026-11-09'])->assertForbidden();
        $this->actingAs($viewer)->post($this->url('/adjustments'), ['amount' => 5, 'entry_date' => '2026-11-09', 'note' => 'x'])->assertForbidden();
        $this->assertSame(0, LedgerEntry::query()->count());

        $this->actingAs($viewer)->get($this->url('/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('finance.balance_label', '0.00 ₪')->where('finance.ledger_url', route('merchants.ledger', $this->merchant->id))->etc());

        $stranger = User::forceCreate(['name' => 'S', 'email' => 's-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Order View List');
        $this->flushSession();
        $this->actingAs($stranger)->get($this->url('/ledger'))->assertForbidden();
    }

    /**
     * +100 on Nov 1, +10 on Nov 2, −40 (cash, TR-77) on Nov 3.
     */
    private function entries(): void
    {
        $ledger = app(MerchantLedger::class);
        $ledger->recordAdjustment($this->merchant, ['amount' => 100, 'entry_date' => '2026-11-01', 'note' => 'رصيد افتتاحي'], $this->admin->id);
        $ledger->recordAdjustment($this->merchant, ['amount' => 10, 'entry_date' => '2026-11-02', 'note' => 'رسوم'], $this->admin->id);
        $ledger->recordPayment($this->merchant, ['amount' => 40, 'method' => 'cash', 'entry_date' => '2026-11-03', 'reference' => 'TR-77', 'note' => 'ملاحظة داخلية'], null, $this->admin->id);
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin/wholesale/merchants/'.$this->merchant->id.$path);
    }
}
