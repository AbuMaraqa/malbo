<?php

namespace Tests\Feature\Reports\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;

/**
 * Orders written straight to the tables, at any status, channel and date: reports only read them.
 */
trait MakesReportData
{
    /**
     * @param  array<string, mixed>  $order  status, channel, created_at, discount, delivery_fee, user_id, branch_id
     * @param  list<array<string, mixed>>  $lines  product?, quantity, price, total?, options?, bundle?, sku?, name?
     */
    protected function sale(array $order, array $lines): Order
    {
        $rows = array_map(fn (array $line) => [
            ...$line,
            'total' => $line['total'] ?? round($line['price'] * $line['quantity'], 2),
        ], $lines);
        $subTotal = round(array_sum(array_column($rows, 'total')), 2);
        $discount = min((float) ($order['discount'] ?? 0), $subTotal);
        $fee = (float) ($order['delivery_fee'] ?? 0);
        $at = Carbon::parse($order['created_at'] ?? now());

        $model = new Order;
        $model->forceFill([
            'order_number' => 'R'.Str::upper(Str::random(10)),
            'user_id' => $order['user_id'] ?? null,
            'branch_id' => $order['branch_id'] ?? null,
            'channel' => ($order['channel'] ?? OrderChannel::B2C)->value,
            'status' => ($order['status'] ?? OrderStatus::DELIVERED)->value,
            'payment_method' => 1,
            'payment_status' => 1,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'delivery_fee' => $fee,
            'total' => round($subTotal - $discount + $fee, 2),
            'delivery_address' => 'شارع',
            'contact_phone' => '0599000000',
            'customer_name' => 'Buyer',
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        foreach ($rows as $row) {
            /** @var Product|null $product */
            $product = $row['product'] ?? null;
            $item = new OrderItem;
            $item->forceFill([
                'order_id' => $model->id,
                'product_id' => $product?->id,
                'price' => $row['price'],
                'quantity' => $row['quantity'],
                'total_price' => $row['total'],
                'sku' => $row['sku'] ?? $product?->sku,
                'product_name' => ['ar' => $row['name'] ?? ($product?->parent ?? $product)?->name ?? 'منتج', 'en' => $row['name_en'] ?? 'Product'],
                'options' => $row['options'] ?? [],
                'bundle_product_id' => $row['bundle']['product']->id ?? null,
                'bundle_group' => $row['bundle']['group'] ?? null,
                'bundle' => isset($row['bundle']) ? ['type' => 'set', 'sku' => $row['bundle']['product']->sku, 'name' => ['ar' => $row['bundle']['name'] ?? 'طقم'], 'quantity' => $row['bundle']['quantity'], 'unit_price' => $row['bundle']['unit_price'] ?? 0] : null,
            ])->save();
        }

        // In the order they were written: tests pick lines by position.
        return $model->load(['items' => fn ($query) => $query->orderBy('id')]);
    }

    /**
     * A completed (by default) return or exchange request over some lines of the order.
     *
     * @param  array<int, int>  $lines  order item id => quantity
     */
    protected function request(Order $order, ReturnRequestType $type, array $lines, ReturnReason $reason = ReturnReason::WRONG_SIZE, ReturnRequestStatus $status = ReturnRequestStatus::COMPLETED): ReturnRequest
    {
        $request = new ReturnRequest;
        $request->forceFill([
            'order_id' => $order->id,
            'type' => $type->value,
            'status' => $status->value,
            'reason' => $reason->value,
            'refund_amount' => 0,
            'completed_at' => $status === ReturnRequestStatus::COMPLETED ? now() : null,
        ])->save();

        foreach ($lines as $itemId => $quantity) {
            $request->lines()->create(['order_item_id' => $itemId, 'quantity' => $quantity]);
        }

        return $request;
    }

    /**
     * A size or colour option as an order line keeps it.
     *
     * @return array{name: array<string, string>, value: array<string, string>}
     */
    protected static function option(string $name, string $value, ?string $nameEn = null, ?string $valueEn = null): array
    {
        return ['name' => array_filter(['ar' => $name, 'en' => $nameEn]), 'value' => array_filter(['ar' => $value, 'en' => $valueEn])];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function reportStaff(array $permissions): User
    {
        return User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::lower(Str::random(10)).'@store.test', 'password' => 'password', 'email_verified_at' => now()])
            ->givePermissionTo($permissions);
    }

    protected function report(string $name, string $permission, string $query = ''): TestResponse
    {
        return $this->actingAs($this->reportStaff([$permission]))->get($this->siteUrl("/ar/admin/reports/{$name}".$query));
    }

    /**
     * The page's props, as the browser reads them.
     *
     * @return array<string, mixed>
     */
    protected function reportProps(string $name, string $permission, string $query = ''): array
    {
        return $this->report($name, $permission, $query)->assertOk()->viewData('page')['props'];
    }

    /**
     * The CSV's rows (BOM checked and removed).
     *
     * @return list<list<string>>
     */
    protected function csv(string $name, string $permission, string $query = ''): array
    {
        $response = $this->report($name, $permission.'', $query)->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(preg_split('/\r?\n/', substr($body, 3)), fn ($line) => $line !== '')));
    }
}
