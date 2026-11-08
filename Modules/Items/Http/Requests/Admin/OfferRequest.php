<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Branch\Entities\Branch;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Enums\OfferType;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\OfferService;

/**
 * Add picks one or more branches (one offer each); Edit is one offer, so one branch.
 */
class OfferRequest extends FormRequest
{
    private const MONEY_MAX = 'max:99999999.99'; // decimal(10, 2)

    private const INT_MAX = 'max:2147483647';

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('offer');
        $branches = (new Branch)->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'], // a varchar column
            ...($id
                ? ['branch_id' => ['required', 'integer', Rule::exists($branches, 'id')]]
                : [
                    'submit_token' => ['required', 'uuid'],
                    'branch_id' => ['required', 'array', 'min:1'],
                    'branch_id.*' => ['integer', Rule::exists($branches, 'id')],
                ]),
            'offerable_model' => ['required', Rule::in(array_keys(OfferService::TARGETS))],
            'offerable_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(OfferType::class)],
            'value' => ['required', 'numeric', 'min:0', self::MONEY_MAX],
            'code' => ['nullable', 'string', 'max:50', Rule::unique((new Offer)->getTable(), 'code')->ignore($id)],
            'min_purchase_amount' => ['required', 'numeric', 'min:0', self::MONEY_MAX],
            'usage_limit' => ['required', 'integer', 'min:1', self::INT_MAX],
            'usage_limit_per_user' => ['required', 'integer', 'min:1', self::INT_MAX],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
            'image' => [$this->imageIsRequired($id) ? 'required' : 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10000'],
            'removed_image' => ['nullable', 'array'],
            'removed_image.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'description' => __('Description'),
            'branch_id' => __('Branch'),
            'branch_id.*' => __('Branch'),
            'offerable_model' => __('Offerable Model'),
            'offerable_id' => __('Offerable ID'),
            'type' => __('Type'),
            'value' => __('Value'),
            'code' => __('Code'),
            'min_purchase_amount' => __('Min Purchase Amount'),
            'usage_limit' => __('Usage Limit'),
            'usage_limit_per_user' => __('Usage Limit Per User'),
            'start_date' => __('Start Date'),
            'end_date' => __('End Date'),
            'image' => __('Image'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $branches = array_map('intval', (array) $this->input('branch_id'));

            // The column is unique and add makes one offer per branch.
            if (filled($this->input('code')) && count($branches) > 1) {
                $validator->errors()->add('code', __('A code can only be used with one branch.'));
            }

            if ((int) $this->input('type') === OfferType::PERCENTAGE->value && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', __('A percentage discount cannot be over 100.'));
            }

            $id = (int) $this->input('offerable_id');
            $exists = $this->input('offerable_model') === 'items_product'
                ? app(ProductRepositoryInterface::class)->isParentInBranches($id, $branches)
                : Category::query()->whereKey($id)->exists();

            if (! $exists) {
                $validator->errors()->add('offerable_id', __('validation.exists', ['attribute' => __('Offerable ID')]));
            }
        }];
    }

    private function imageIsRequired(int $id): bool
    {
        if (! $id) {
            return true;
        }

        $saved = app(OfferRepositoryInterface::class)->findForForm($id)->getFirstMedia('offer');

        return ! $saved || in_array($saved->id, array_map('intval', (array) $this->input('removed_image', [])), true);
    }
}
