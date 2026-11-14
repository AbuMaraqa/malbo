<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Events\ProductsBecameOrderable;
use Modules\Items\Exceptions\InsufficientStock;
use Modules\Items\Notifications\LowStockAlert;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;

/**
 * The only code that changes `stock_quantity`. The store and the wholesale portal sell from the
 * same stock, so every change locks the product rows (in id order), writes a StockMovement and keeps
 * `stock_status` in step. A sale never takes more than there is, unless the product allows
 * backorders; a product whose stock is not managed is left alone.
 *
 * Incoming goods (`incoming_quantity`, expected on `incoming_expected_at`) are written here too.
 * Only a wholesale sale (sellIncludingIncoming) may reserve them: it takes the stock below 0, down
 * to minus the dated incoming pieces. A plain sale (the storefront) never goes below 0, so it never
 * sells them. Receiving goods counts them off the incoming pieces.
 */
class InventoryService
{
    /**
     * Products that fell to their low-stock threshold or below in this process, after the commit.
     *
     * @var list<int>
     */
    public array $lowStock = [];

    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * Sells these quantities (product id => quantity). A negative quantity gives stock back, for an
     * order line that was lowered.
     *
     * @param  array<int, int>  $quantities
     *
     * @throws InsufficientStock
     */
    public function sell(array $quantities, ?Order $order = null, ?int $userId = null): void
    {
        $this->apply(array_map(fn (int $quantity) => -$quantity, $quantities), StockMovementType::SALE, $order, $userId);
    }

    /**
     * A wholesale sale, which may reserve incoming goods: each product's stock may go below 0, down
     * to minus its reservable incoming pieces (reservableIncoming), checked on the locked row. Never
     * for the storefront.
     *
     * @param  array<int, int>  $quantities  product id => pieces (positive)
     * @return array<int, int> product id => pieces taken beyond the stock (pre-ordered); only those above 0
     *
     * @throws InsufficientStock
     */
    public function sellIncludingIncoming(array $quantities, ?Order $order = null, ?int $userId = null): array
    {
        $preorders = [];

        $this->apply(array_map(fn (int $quantity) => -abs($quantity), $quantities), StockMovementType::SALE, $order, $userId, incoming: true, preorders: $preorders);

        return array_filter($preorders);
    }

    /**
     * The incoming pieces a merchant may reserve: `incoming_quantity` expected today or later, else 0.
     * Goods with no expected date, or a date gone by (late: staff set a new one), are not offered.
     * Pieces already reserved stay reserved.
     */
    public static function reservableIncoming(Product $product): int
    {
        $date = $product->incoming_expected_at;

        return $date !== null && ! $date->copy()->startOfDay()->lt(now()->startOfDay()) ? max(0, (int) $product->incoming_quantity) : 0;
    }

    /**
     * Sets what is on its way: $quantity pieces expected on $expectedAt (null: no date, not offered).
     * Never below what merchants already reserved (the stock below 0). With $loaded (what the form
     * showed when it was opened), refused when the row changed since, for example goods received
     * meanwhile: the form would otherwise put back pieces that already arrived.
     *
     * @param  array{quantity: int, expected_at: ?string}|null  $loaded
     *
     * @throws ValidationException on `incoming_quantity` or `product_id` (not sellable)
     */
    public function setIncoming(Product $product, int $quantity, ?string $expectedAt, ?int $userId = null, ?array $loaded = null): void
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['incoming_quantity' => __('Enter 0 or more.')]);
        }

        $this->assertSellable($product);

        DB::transaction(function () use ($product, $quantity, $expectedAt, $loaded): void {
            $locked = $this->products->lockForStock([$product->id])->first();

            if (! $locked) {
                return;
            }

            if ($loaded !== null && ((int) $loaded['quantity'] !== (int) $locked->incoming_quantity || ($loaded['expected_at'] ?: null) !== $locked->incoming_expected_at?->toDateString())) {
                throw ValidationException::withMessages(['incoming_quantity' => __('This changed since you opened this page: :count are on their way now. Reload and enter it again.', ['count' => (int) $locked->incoming_quantity])]);
            }

            $reserved = max(0, -(int) $locked->stock_quantity);

            if ($quantity < $reserved) {
                throw ValidationException::withMessages(['incoming_quantity' => __('Merchants already reserved :count of these pieces: enter :count or more.', ['count' => $reserved])]);
            }

            $locked->forceFill([
                'incoming_quantity' => $quantity,
                'incoming_expected_at' => $quantity > 0 && filled($expectedAt) ? $expectedAt : null,
            ])->save();

            $product->setRawAttributes($locked->fresh()->getAttributes(), true);
        });
    }

    /**
     * Gives these quantities (product id => positive quantity) back: a cancellation or a return.
     *
     * @param  array<int, int>  $quantities
     */
    public function restock(array $quantities, StockMovementType $type, ?Order $order = null, ?int $userId = null): void
    {
        $this->apply(array_map(fn (int $quantity) => abs($quantity), $quantities), $type, $order, $userId);
    }

    /**
     * Sets the stock to $quantity (an adjustment or incoming goods) and records the difference. Also
     * for a product whose stock is not managed: the number typed in the form is kept.
     */
    public function setLevel(Product $product, int $quantity, StockMovementType $type, ?string $note = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $quantity, $type, $note, $userId): void {
            $locked = $this->products->lockForStock([$product->id])->first();

            if ($locked && $locked->stock_quantity === null) {
                // A new row has no number yet: it starts at 0 (sold out), so a level of 0 is stored
                // as one, not left empty and "in stock".
                $locked->forceFill([
                    'stock_quantity' => 0,
                    ...($locked->manage_stock ? ['stock_status' => $locked->allow_backorder ? 'on_backorder' : 'out_of_stock'] : []),
                ])->save();
            }

            if ($locked) {
                $this->apply([$locked->id => $quantity - (int) $locked->stock_quantity], $type, null, $userId, $note, unmanagedToo: true);
                $product->setRawAttributes($locked->fresh()->getAttributes(), true);
            }
        });
    }

    /**
     * Goods that arrived: adds $quantity to whatever the stock is now (an INCOMING movement), so
     * units sold meanwhile stay sold, and counts them off the incoming pieces (never below 0; the
     * expected date goes once none are left). Only a product that holds stock (a variant, or a
     * product without variants); also for one whose stock is not managed, as setLevel().
     *
     * @throws ValidationException on `quantity` (not above 0) or `product_id` (not sellable)
     */
    public function receive(Product $product, int $quantity, ?string $note, ?int $userId): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => __('Enter a quantity above 0.')]);
        }

        $this->assertSellable($product);

        DB::transaction(function () use ($product, $quantity, $note, $userId): void {
            $locked = $this->products->lockForStock([$product->id])->first();

            if ($locked) {
                $this->apply([$locked->id => $quantity], StockMovementType::INCOMING, null, $userId, self::note($note), unmanagedToo: true);

                $fresh = $locked->fresh();

                if ((int) $fresh->incoming_quantity > 0) {
                    $left = max(0, (int) $fresh->incoming_quantity - $quantity);
                    $fresh->forceFill(['incoming_quantity' => $left, 'incoming_expected_at' => $left > 0 ? $fresh->incoming_expected_at : null])->save();
                }

                $product->setRawAttributes($fresh->getAttributes(), true);
            }
        });
    }

    /**
     * A manual correction: moves the stock by $delta (an ADJUST movement, the reason as its note).
     * Checked on the locked row: managed stock without backorders never goes below 0.
     *
     * @throws ValidationException on `delta` (0, or below zero), `reason` (empty) or `product_id` (not sellable)
     */
    public function adjust(Product $product, int $delta, string $reason, ?int $userId): void
    {
        if ($delta === 0) {
            throw ValidationException::withMessages(['delta' => __('Enter a change other than 0.')]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('Enter the reason for this adjustment.')]);
        }

        $this->assertSellable($product);

        DB::transaction(function () use ($product, $delta, $reason, $userId): void {
            $locked = $this->products->lockForStock([$product->id])->first();

            if (! $locked) {
                return;
            }

            $current = (int) $locked->stock_quantity;

            if ($current + $delta < 0 && $locked->manage_stock && ! $locked->allow_backorder) {
                throw ValidationException::withMessages(['delta' => __('The stock is :count: this change would take it below 0.', ['count' => $current])]);
            }

            $this->apply([$locked->id => $delta], StockMovementType::ADJUST, null, $userId, self::note($reason), unmanagedToo: true);
            $product->setRawAttributes($locked->fresh()->getAttributes(), true);
        });
    }

    /**
     * Whether an item can be ordered now: not marked out of stock, and its stock is not managed,
     * allows backorders, or is above 0. The storefront's rule (Store's ProductConfigurator::available);
     * a change from false to true is announced (ProductsBecameOrderable).
     */
    public static function orderable(Product $product): bool
    {
        if ($product->stock_status === 'out_of_stock') {
            return false;
        }

        return ! $product->manage_stock || $product->allow_backorder || (int) $product->stock_quantity > 0;
    }

    /**
     * @throws ValidationException on `product_id`
     */
    private function assertSellable(Product $product): void
    {
        if (! $this->products->isSellable($product->id)) {
            throw ValidationException::withMessages(['product_id' => __('Choose a variant, or a product without variants: stock is kept per variant.')]);
        }
    }

    /**
     * The ledger's note column holds 255 characters.
     */
    private static function note(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 255);
    }

    /**
     * A stock number saved from the product form. $loaded is what the form showed when it was
     * opened. A new product's number is its opening stock. On an existing product only a change the
     * admin typed moves stock, applied as a difference to the current stock, so units sold while the
     * form was open stay sold. A save without $loaded (an old client) leaves the stock alone.
     *
     * @throws ValidationException when the change no longer fits the current stock
     */
    public function fromForm(Product $product, ?int $typed, ?int $loaded, bool $isNew, ?int $userId = null): void
    {
        if ($typed === null) {
            return;
        }

        if ($isNew) {
            $this->setLevel($product, $typed, StockMovementType::INCOMING, null, $userId);

            return;
        }

        if ($loaded === null || $typed === $loaded) {
            return;
        }

        DB::transaction(function () use ($product, $typed, $loaded, $userId): void {
            $locked = $this->products->lockForStock([$product->id])->first();

            if (! $locked) {
                return;
            }

            $current = (int) $locked->stock_quantity;

            if ($current + ($typed - $loaded) < 0 && $locked->manage_stock && ! $locked->allow_backorder) {
                throw ValidationException::withMessages(['stock_quantity' => __('The stock changed while this page was open: it is now :count. Enter the stock again.', ['count' => $current])]);
            }

            $this->apply([$locked->id => $typed - $loaded], StockMovementType::ADJUST, null, $userId, __('Changed in the product form'), unmanagedToo: true);
            $product->setRawAttributes($locked->fresh()->getAttributes(), true);
        });
    }

    /**
     * @param  array<int, int>  $changes  product id => signed change
     * @param  bool  $incoming  a sale may take the stock down to minus the reservable incoming pieces
     * @param  array<int, int>  $preorders  filled with product id => pieces a sale took beyond the stock
     *
     * @throws InsufficientStock
     */
    private function apply(array $changes, StockMovementType $type, ?Order $order, ?int $userId, ?string $note = null, bool $unmanagedToo = false, bool $incoming = false, array &$preorders = []): void
    {
        $changes = array_filter(array_map('intval', $changes));

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($changes, $type, $order, $userId, $note, $unmanagedToo, $incoming, &$preorders): void {
            $orderableAgain = [];

            foreach ($this->products->lockForStock(array_keys($changes)) as $product) {
                if (! $product->manage_stock && ! $unmanagedToo) {
                    continue;
                }

                $change = $changes[$product->id];
                $before = (int) $product->stock_quantity;
                $after = $before + $change;

                $floor = $incoming ? -self::reservableIncoming($product) : 0;

                if ($type === StockMovementType::SALE && $change < 0 && $after < $floor && ! $product->allow_backorder) {
                    throw InsufficientStock::for($product, $before - $floor);
                }

                if ($incoming && $change < 0 && ! $product->allow_backorder) {
                    // What the stock could not cover is a reservation of the incoming goods.
                    $preorders[$product->id] = -$change - max(0, min(-$change, $before));
                }

                $wasOrderable = self::orderable($product);

                $product->forceFill([
                    'stock_quantity' => $after,
                    // Stock that is not managed keeps the number typed but never decides whether the
                    // product sells: its status is left as the product form set it.
                    ...($product->manage_stock ? ['stock_status' => $after > 0 ? 'in_stock' : ($product->allow_backorder ? 'on_backorder' : 'out_of_stock')] : []),
                ])->save();

                if (! $wasOrderable && self::orderable($product)) {
                    $orderableAgain[] = (int) $product->id;
                }

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => $change,
                    'balance_after' => $after,
                    'order_id' => $order?->id,
                    'note' => $note,
                    'created_by' => $userId,
                ]);

                $threshold = (int) $product->low_stock_threshold;

                if ($product->manage_stock && $before > $threshold && $after <= $threshold) {
                    DB::afterCommit(function () use ($product): void {
                        $this->lowStock[] = $product->id;
                        // Once the response is out: the person selling never waits for the staff's messages.
                        defer(fn () => LowStockAlert::send($product), always: true);
                    });
                }
            }

            if ($orderableAgain !== []) {
                // Back-in-stock alerts and the like: only once the change is really there.
                DB::afterCommit(fn () => event(new ProductsBecameOrderable($orderableAgain)));
            }
        });
    }
}
