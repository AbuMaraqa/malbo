<?php

namespace Modules\Wholesale\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\MerchantStatusChange;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Http\Requests\Admin\MerchantRequest;
use Modules\Wholesale\Http\Requests\Admin\MerchantStatusRequest;
use Modules\Wholesale\Http\Requests\Admin\MerchantStoreRequest;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Services\MerchantApplicationService;
use Modules\Wholesale\Services\MerchantService;
use Modules\Wholesale\Settings\WholesaleSettings;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wholesale merchants: the list, adding one (its account starts approved), and a merchant's page
 * (profile, register document, status with a note, tier, sales rep, minimum order, history).
 */
class MerchantController extends Controller
{
    /** How long a document link works. */
    private const LINK_MINUTES = 10;

    public function __construct(
        private readonly MerchantRepositoryInterface $merchants,
        private readonly MerchantService $merchantService,
        private readonly TierRepositoryInterface $tiers,
        private readonly CityRepositoryInterface $cities,
        private readonly LedgerEntryRepositoryInterface $ledger,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Wholesale/Merchant/Index', [
            'table' => AdminTable::for($this->merchants->tableQuery())
                ->search(['shop_name', 'owner_name', 'phone', 'email', 'commercial_register'], null)
                ->filters(
                    AdminFilter::exact('status'),
                    AllowedFilter::callback('tier_id', fn (Builder $query, mixed $value) => $value === 'none'
                        ? $query->whereNull('tier_id')
                        : $query->where('tier_id', (int) $value)),
                    AdminFilter::exact('sales_rep_id'),
                    AdminFilter::exact('city_id'),
                    AdminFilter::text('shop_name'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('shop_name', 'owner_name', 'status', 'created_at', 'id')
                ->rows(fn (Merchant $merchant) => [
                    'id' => $merchant->id,
                    'shop_name' => $merchant->shop_name,
                    'owner_name' => $merchant->owner_name,
                    'phone' => $merchant->phone,
                    'email' => $merchant->email,
                    'city' => $merchant->city?->name,
                    'status' => $merchant->status->value,
                    'status_label' => $merchant->status->getLabel(),
                    'status_tone' => $merchant->status->tone(),
                    'tier' => $merchant->tier?->name,
                    'sales_rep' => $merchant->salesRep?->name,
                    'created_at' => $merchant->created_at?->format('Y-m-d H:i'),
                ]),
            'statuses' => MerchantStatus::options(),
            'tiers' => fn () => $this->tiers->options(),
            'salesReps' => fn () => $this->merchants->salesRepOptions(),
            'cities' => fn () => $this->cityOptions(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Wholesale/Merchant/Add', [
            'storeMinimum' => $this->storeMinimum(),
            'tiers' => $this->tiers->options(),
            'salesReps' => $this->merchants->salesRepOptions(),
            'cities' => $this->cityOptions(),
        ]);
    }

    public function store(MerchantStoreRequest $request): RedirectResponse
    {
        $merchant = $this->merchantService->create($request->merchantData(), (int) $request->user()->id);

        return redirect()->route('merchants.edit', $merchant->id)->with('success', __('Merchant added successfully'));
    }

    public function edit(int $merchant): Response
    {
        $model = $this->merchants->forEdit($merchant);

        return Inertia::render('Wholesale/Merchant/Edit', [
            'merchant' => [
                'id' => $model->id,
                'shop_name' => $model->shop_name,
                'owner_name' => $model->owner_name,
                'city_id' => $model->city_id,
                'phone' => $model->phone,
                'email' => $model->email,
                'commercial_register' => $model->commercial_register,
                'shop_address' => $model->shop_address,
                'tier_id' => $model->tier_id,
                'sales_rep_id' => $model->sales_rep_id,
                'minimum_order_value' => $model->minimum_order_value,
                'minimum_order_pieces' => $model->minimum_order_pieces,
                'credit_enabled' => (bool) $model->credit_enabled,
                'credit_limit' => $model->credit_limit,
            ],
            'status' => [
                'value' => $model->status->value,
                'label' => $model->status->getLabel(),
                'tone' => $model->status->tone(),
                'next' => array_map(fn (MerchantStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->getLabel(),
                    'needs_note' => $status->needsNote(),
                ], $model->status->next()),
                'approved_at' => $model->approved_at?->format('Y-m-d H:i'),
            ],
            'document' => $model->register_document_path ? [
                'name' => $model->register_document_name ?: basename($model->register_document_path),
                'url' => URL::temporarySignedRoute('merchants.document', now()->addMinutes(self::LINK_MINUTES), ['merchant' => $model->id]),
            ] : null,
            'history' => $model->statusChanges->map(fn (MerchantStatusChange $change) => [
                'id' => $change->id,
                'from' => $change->from_status?->getLabel(),
                'to' => $change->to_status->getLabel(),
                'note' => $change->note,
                'by' => $change->createdBy?->name,
                'at' => $change->created_at?->format('Y-m-d H:i'),
            ])->values()->all(),
            'account' => [
                'name' => $model->user?->name,
                'email' => $model->user?->email,
                'created_at' => $model->created_at?->format('Y-m-d H:i'),
            ],
            'storeMinimum' => $this->storeMinimum(),
            // The account in brief; its page holds the ledger.
            'finance' => [
                'balance_label' => (new Price($this->ledger->balance((int) $model->id)))->format(),
                'ledger_url' => route('merchants.ledger', $model->id),
            ],
            'tiers' => $this->tiers->options(),
            'salesReps' => $this->merchants->salesRepOptions(),
            'cities' => $this->cityOptions(),
        ]);
    }

    public function update(MerchantRequest $request, int $merchant): RedirectResponse
    {
        $this->merchantService->update($this->merchants->forEdit($merchant), $request->merchantData());

        return back()->with('success', __('Merchant updated successfully'));
    }

    public function status(MerchantStatusRequest $request, int $merchant): RedirectResponse
    {
        $data = $request->validated();

        $this->merchantService->changeStatus($this->merchants->forEdit($merchant), MerchantStatus::from($data['status']), $data['note'] ?? null, (int) $request->user()->id);

        return back()->with('success', __('Merchant status updated'));
    }

    /**
     * The register's scan, through a link the edit page signed a few minutes ago.
     */
    public function document(Request $request, int $merchant): StreamedResponse
    {
        $model = $this->merchants->forEdit($merchant);
        $path = $model->register_document_path;

        abort_if(blank($path) || ! Storage::disk(MerchantApplicationService::DISK)->exists($path), 404);

        return Storage::disk(MerchantApplicationService::DISK)->download($path, $model->register_document_name ?: basename($path));
    }

    /**
     * What applies when the merchant has no minimum of its own.
     *
     * @return array{value: ?string, pieces: ?int}
     */
    private function storeMinimum(): array
    {
        $settings = app(WholesaleSettings::class);

        return [
            'value' => $settings->minimum_order_value !== null ? (new Price((float) $settings->minimum_order_value))->format() : null,
            'pieces' => $settings->minimum_order_pieces,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function cityOptions(): array
    {
        return array_map(fn (array $city) => ['value' => $city['value'], 'label' => $city['label']], $this->cities->options());
    }
}
