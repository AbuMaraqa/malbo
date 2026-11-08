<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Http\Requests\OrderRequest;
use Modules\Items\Http\Requests\UpdateOrderStatusRequest;
use Modules\Items\Transformers\V1\OrderResource;
use Modules\Store\Services\PricingService;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/items/orders",
     * summary="Get all orders",
     * description="Retrieve a list of orders for the authenticated user, with support for filtering, sorting, and pagination.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(
     * type="string",
     * default="ar",
     * example="en"
     * )
     * ),
     * @OA\Parameter(
     *  name="filter[status]",
     *  in="query",
     *  required=false,
     *  description="Filter orders by status ID (e.g., 1 for Pending, 2 for Confirmed)",
     *  @OA\Schema(
     *  type="integer",
     *  enum={1, 2, 3, 4, 5, 6},
     *  example=1
     *  )
     *  ),
     *  @OA\Parameter(
     *  name="filter[user_id]",
     *  in="query",
     *  required=false,
     *  description="Filter orders by user ID",
     *  @OA\Schema(
     *  type="integer",
     *  example=1
     *  )
     *  ),
     *      @OA\Property(
     *  property="payment_method",
     *  type="integer",
     *  description="طريقة الدفع: 1 = الدفع عند الاستلام, 2 = بطاقة ائتمانية, 3 = باي بال",
     *  enum={1, 2, 3},
     *  example=1
     *  ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources. e.g., 'user', 'items', 'items.product'",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by a field. e.g., 'created_at' for oldest, '-created_at' for newest.",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page.",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number.",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Orders retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Orders retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=101),
     * @OA\Property(property="order_number", type="string", example="ORD-ABC12345"),
     * @OA\Property(property="total", type="number", format="float", example=250.50),
     * @OA\Property(property="status", type="string", example="قيد التحضير"),
     * @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-12 14:02:00")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */

    public function index(Request $request)
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = max(1, min((int) request('per_page', $defaultPerPage), $maxPerPage));

        // A buyer's own orders, whatever the filters say. All orders need the orders page's permission.
        $orders = Order::query()->when(
            ! $request->user()->can('Order View List'),
            fn ($query) => $query->where('user_id', $request->user()->id),
        );

        $ordersQuery = QueryBuilder::for($orders)
            ->allowedFields(...OrderResource::allowedFields())
            ->allowedFilters(...OrderResource::allowedFilters())
            ->allowedSorts(...OrderResource::allowedSorts())
            ->allowedIncludes(...OrderResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            OrderResource::collection($ordersQuery),
            __('Orders retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/items/orders",
     * summary="Create a new order",
     * description="Create a new order for the authenticated user, with optional add-ons for each item.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"delivery_address", "contact_phone", "payment_method", "items"},
     * @OA\Property(property="delivery_address", type="string", example="123 Main St, Ramallah"),
     * @OA\Property(property="contact_phone", type="string", example="+970599123456"),
     * @OA\Property(property="payment_method", type="string", example="cash_on_delivery"),
     * @OA\Property(property="notes", type="string", example="Please call before arriving."),
     * @OA\Property(
     * property="items",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"product_id", "quantity"},
     * @OA\Property(property="product_id", type="integer", example=33),
     * @OA\Property(property="quantity", type="integer", example=1),
     *
     *
     * @OA\Property(
     * property="addons",
     * type="array",
     * description="An optional array of add-ons for this specific item.",
     * @OA\Items(
     * type="object",
     * required={"addon_id", "quantity"},
     * @OA\Property(property="addon_id", type="integer", example=5, description="ID of the addon"),
     * @OA\Property(property="quantity", type="integer", example=2, description="Quantity of the addon")
     * )
     * ),
     *
     *
     * )
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Order created successfully"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(OrderRequest $request, PricingService $pricing)
    {
        $validatedData = $request->validated();
        $itemsData = $validatedData['items'];
        $tablePrefix = config('items.table_prefix');
        $branchId = (int) $validatedData['branch_id'];

        $productIds = collect($itemsData)->pluck('product_id')->unique()->values();

        $products = Product::with(['parent.addons', 'addons', 'activeOffers'])->findMany($productIds)->keyBy('id');

        $addonOptionIds = collect($itemsData)->pluck('addons.*.addon_option_id')->flatten()->filter()->unique()->values();
        $addonOptions = AddonOption::findMany($addonOptionIds)->keyBy('id');

        $parentProductIds = $products->pluck('parent_id')->filter()->unique();
        $allProductIdsForLookup = $productIds->merge($parentProductIds)->unique();
        $customPricesMap = $this->preloadCustomPrices($allProductIdsForLookup, $addonOptionIds, $tablePrefix);

        $subTotal = 0;
        $itemsToCreate = [];

        // Every amount below comes from the database: the request only says what and how many.
        foreach ($itemsData as $index => $item) {
            $product = $products->get($item['product_id']);
            $sellable = $product?->parent ?? $product;

            // An order must never silently lose a line, or include what the store does not sell.
            if (! $product || $sellable->status !== ProductStatus::PUBLISHED || ! $sellable->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => __('This product is not available.')]);
            }

            $finalProductPrice = $this->unitPrice($product, $pricing, $branchId);
            $itemTotalPrice = $finalProductPrice * $item['quantity'];
            $optionsToAttach = [];

            if (! empty($item['addons'])) {
                $productIdForAddonLookup = $product->parent_id ?? $product->id;
                $offeredAddons = $sellable->addons->pluck('id');

                foreach ($item['addons'] as $addonIndex => $addonData) {
                    $optionId = (int) $addonData['addon_option_id'];
                    $addonOption = $addonOptions->get($optionId);

                    if (! $addonOption || ! $offeredAddons->contains($addonOption->addon_id)) {
                        throw ValidationException::withMessages(["items.{$index}.addons.{$addonIndex}.addon_option_id" => __('This option is not available for this product.')]);
                    }

                    $finalAddonPrice = $customPricesMap[$productIdForAddonLookup][$optionId] ?? (float) $addonOption->price;

                    $itemTotalPrice += $finalAddonPrice * $addonData['quantity'];

                    $optionsToAttach[$optionId] = [
                        'quantity' => $addonData['quantity'],
                        'price'    => $finalAddonPrice,
                    ];
                }
            }

            $subTotal += $itemTotalPrice;

            $itemsToCreate[] = [
                'product_id'      => $product->id,
                'quantity'        => $item['quantity'],
                'price'           => $finalProductPrice,
                'total_price'     => $itemTotalPrice,
                'notes'           => $item['notes'] ?? null,
                'options_to_attach' => $optionsToAttach,
            ];
        }

        $order = null;

        try {
            DB::transaction(function () use ($validatedData, $subTotal, $itemsToCreate, &$order) {
                // No delivery fee or coupon exists yet: nothing from the request can change the total.
                $deliveryFee = 0;
                $discount = 0;
                $subTotalAfterDiscount = max(0, $subTotal - $discount);

                $order = Order::create([
                    'user_id'          => auth()->id(),
                    'order_number'     => $this->generateOrderNumber(),
                    'delivery_address' => $validatedData['delivery_address'] ?? null,
                    'branch_id'        => $validatedData['branch_id'],
                    'contact_phone'    => $validatedData['contact_phone'] ?? null,
                    'payment_method'   => $validatedData['payment_method'],
                    'notes'            => $validatedData['notes'] ?? null,
                    'delivery_type'    => $validatedData['delivery_type'],

                    'sub_total'        => $subTotalAfterDiscount,
                    'delivery_fee'     => $deliveryFee,
                    'discount'         => $discount,
                    'total'            => $subTotalAfterDiscount + $deliveryFee,

                    'status'           => OrderStatus::PENDING,
                    'payment_status'   => PaymentStatus::UNPAID,
                    'created_by'       => auth()->id(),
                ]);

                foreach ($itemsToCreate as $itemData) {
                    $orderItem = $order->items()->create([
                        'product_id'     => $itemData['product_id'],
                        'quantity'       => $itemData['quantity'],
                        'price'          => $itemData['price'],
                        'total_price'    => $itemData['total_price'],
                        'notes'          => $itemData['notes'],
                    ]);

                    if (! empty($itemData['options_to_attach'])) {
                        $orderItem->addonOptions()->attach($itemData['options_to_attach']);
                    }
                }
            });
        } catch (\Exception $e) {
            report($e);

            return $this->errorResponse(__('The order could not be created. Please try again.'), 500);
        }

        $order->load([
            'user',
            'items.product.media',
            'items.product.translations',
            'items.addonOptions.media',
            'items.addonOptions.translations',
        ]);

        // The order is saved: a failing notification must not tell the buyer to place it again.
        rescue(fn () => $order->user?->notify(new GeneralNotification(
            title: __('New Order Created'),
            message: __('Your order #:order_number has been created successfully.', ['order_number' => $order->order_number]),
        )));

        return $this->successResponse(
            new OrderResource($order),
            __('Order created successfully'),
            201
        );
    }

    /**
     * The price of one unit: the product's own sale and discount, then its running offer. An offer
     * for another branch does not apply; a free shipping offer does not change the price.
     */
    private function unitPrice(Product $product, PricingService $pricing, int $branchId): float
    {
        $price = $pricing->for($product)->amount;

        $offer = $product->activeOffers->first(fn ($offer) => $offer->branch_id === null || (int) $offer->branch_id === $branchId);

        $price -= match ($offer?->type) {
            OfferType::PERCENTAGE => $price * min((float) $offer->value, 100) / 100,
            OfferType::FIXED => (float) $offer->value,
            default => 0,
        };

        return round(max($price, 0), 2);
    }

    private function preloadCustomPrices($productIds, $addonOptionIds, $tablePrefix): array
    {
        $customizations = DB::table("{$tablePrefix}addon_product_option AS apo")
            ->select('apo.price', 'apo.addon_option_id', 'ap.product_id')
            ->join("{$tablePrefix}addon_product AS ap", 'ap.id', '=', 'apo.addon_product_id')
            ->whereIn('ap.product_id', $productIds)
            ->whereIn('apo.addon_option_id', $addonOptionIds)
            ->whereNotNull('apo.price')
            ->get();

        $map = [];
        foreach ($customizations as $c) {
            $map[$c->product_id][$c->addon_option_id] = (float) $c->price;
        }
        return $map;
    }

    /**
     * دالة مساعدة لإنشاء رقم الطلب (للحفاظ على نظافة دالة store)
     */
    private function generateOrderNumber(): string
    {
        $order_id_to_use = ((int) Order::query()->max('id')) + 1;
        $now = now();
        $datePart = $now->format('ydm');
        $timePart = $now->format('Hi');
        return $datePart . $timePart . $order_id_to_use;
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/orders/{order}",
     * summary="Get a single order",
     * description="Retrieve details of a specific order by ID. Users can only view their own orders.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="order", in="path", required=true, description="The ID of the order", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Order retrieved successfully"),
     * @OA\Response(response=403, description="Forbidden/Access Denied"),
     * @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show(Order $order)
    {
        if (auth()->id() !== $order->user_id && ! auth()->user()->can('Order View List')) {
            return $this->errorResponse(__('Access Denied'), 403);
        }

        $order->load([
            'user',
            'items.product.media',
            'items.product.translations',
            'items.addonOptions.media',
            'items.addonOptions.translations',
        ]);

        return $this->successResponse(
            new OrderResource($order),
            __('Order retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/items/orders/{order}/status",
     * summary="Update order status",
     * description="Change the status of an order. Logic validation is handled via Request.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="order", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"status"},
     * @OA\Property(property="status", type="integer", description="New status ID", example=4),
     * @OA\Property(property="note", type="string", description="Optional note", example="Driver assigned")
     * )
     * ),
     * @OA\Response(response=200, description="Status updated successfully"),
     * @OA\Response(response=422, description="Invalid status transition")
     * )
     */
    public function updateOrderStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        try {
            DB::transaction(function () use ($request, $order) {
                $newStatus = OrderStatus::from((int) $request->status);
                $oldStatus = $order->status;

                $order->update([
                    'status' => $newStatus
                ]);

                if ($order->user_id && $oldStatus != $newStatus) {
                    $order->user->notify(new GeneralNotification(
                        title: __('Order Status Updated'),
                        message: __('Your order #:order_number status is now :status', [
                            'order_number' => $order->order_number,
                            'status'       => $newStatus->getLabel()
                        ]),
                        icon: 'bell',
                        color: 'text-primary',
                    ));
                }
            });
        } catch (\Exception $e) {
            report($e);

            return $this->errorResponse(__('The order could not be updated. Please try again.'), 500);
        }

        return $this->successResponse(
            new OrderResource($order->fresh()),
            __('Order status updated successfully')
        );
    }
}
