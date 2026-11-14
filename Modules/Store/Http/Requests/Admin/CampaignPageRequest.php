<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Items\Entities\Category;
use Modules\Store\Entities\CampaignBlock;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Enums\CampaignBlockType;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;

/**
 * A campaign page's form: its texts in the language being edited, slug, publish state and dates, and
 * its blocks in order. A link is a web address (https:// or http://) or a path on this site
 * ("/ar/shop"); nothing else (no `javascript:`, no `//other.site`, no `/\other.site`, no backslash).
 */
class CampaignPageRequest extends FormRequest
{
    public const MAX_BLOCKS = 30;

    /** A web address, or a path on this site: one "/" then no "/" or "\" (browsers read "/\x" as "//x"). */
    public const LINK = '/^(https?:\/\/\S+|\/(?![\/\\\\])[^\s\\\\]*)$/i';

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'slug' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in([CampaignPage::DRAFT, CampaignPage::PUBLISHED])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'blocks' => ['nullable', 'array', 'max:'.self::MAX_BLOCKS],
            'blocks.*.id' => ['nullable', 'integer'],
            'blocks.*.type' => ['required', Rule::enum(CampaignBlockType::class)],
            'blocks.*.settings' => ['nullable', 'array'],
            'blocks.*.settings.link' => ['nullable', 'string', 'max:500', 'regex:'.self::LINK],
            'blocks.*.settings.source' => ['nullable', Rule::in(['picked', 'category'])],
            'blocks.*.settings.product_ids' => ['nullable', 'array', 'max:24'],
            'blocks.*.settings.product_ids.*' => ['integer'],
            'blocks.*.settings.category_id' => ['nullable', 'integer', Rule::exists((new Category)->getTable(), 'id')],
            'blocks.*.settings.limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'blocks.*.settings.ends_at' => ['nullable', 'date'],
            'blocks.*.content' => ['nullable', 'array'],
            'blocks.*.content.html' => ['nullable', 'string', 'max:50000'],
            'blocks.*.content.heading' => ['nullable', 'string', 'max:160'],
            'blocks.*.content.text' => ['nullable', 'string', 'max:1000'],
            'blocks.*.content.button_label' => ['nullable', 'string', 'max:60'],
            'blocks.*.content.alt' => ['nullable', 'string', 'max:160'],
            'blocks.*.content.caption' => ['nullable', 'string', 'max:300'],
            'blocks.*.image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5000', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /**
     * What each type needs: a picture for an image or banner (sent now, or kept from before), a link
     * and a button text for a call to action, an end for a countdown, products or a category for a
     * product grid.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $withImage = $this->savedBlocksWithImage();

            foreach (array_values((array) $this->input('blocks', [])) as $index => $block) {
                $type = CampaignBlockType::tryFrom((string) ($block['type'] ?? ''));
                $settings = (array) ($block['settings'] ?? []);
                $content = (array) ($block['content'] ?? []);
                $missing = fn (string $field) => $validator->errors()->add("blocks.{$index}.{$field}", __('validation.required', ['attribute' => __(self::LABELS[$field] ?? $field)]));

                // A saved picture counts only for the same block keeping its type: a block whose type
                // changed is replaced by a new one (CampaignPageService), without it.
                $kept = ($withImage[(int) ($block['id'] ?? 0)] ?? null) === $type?->value;

                if ($type?->hasImage() && ! $this->hasFile("blocks.{$index}.image") && ! $kept) {
                    $missing('image');
                }

                if ($type === CampaignBlockType::CTA) {
                    blank($settings['link'] ?? null) && $missing('settings.link');
                    blank($content['button_label'] ?? null) && $missing('content.button_label');
                }

                if ($type === CampaignBlockType::COUNTDOWN && blank($settings['ends_at'] ?? null)) {
                    $missing('settings.ends_at');
                }

                if ($type === CampaignBlockType::PRODUCTS) {
                    ($settings['source'] ?? 'picked') === 'category'
                        ? (blank($settings['category_id'] ?? null) && $missing('settings.category_id'))
                        : (empty($settings['product_ids']) && $missing('settings.product_ids'));
                }
            }
        }];
    }

    private const LABELS = [
        'image' => 'Picture',
        'settings.link' => 'Link',
        'settings.ends_at' => 'Ends at',
        'settings.category_id' => 'Category',
        'settings.product_ids' => 'Products',
        'content.button_label' => 'Button text',
    ];

    /**
     * This page's saved blocks that already hold a picture, with their type.
     *
     * @return array<int, string>  block id => type
     */
    private function savedBlocksWithImage(): array
    {
        $pageId = (int) $this->route('page');

        if ($pageId === 0) {
            return [];
        }

        return app(CampaignPageRepositoryInterface::class)->withBlocks($pageId)->blocks
            ->filter(fn (CampaignBlock $block) => $block->getFirstMedia('image') !== null)
            ->mapWithKeys(fn (CampaignBlock $block) => [(int) $block->id => (string) $block->type])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Title'),
            'seo_title' => __('Search engine title'),
            'seo_description' => __('Search engine description'),
            'slug' => __('Link'),
            'starts_at' => __('Starts at'),
            'ends_at' => __('Ends at'),
            'blocks.*.settings.link' => __('Link'),
            'blocks.*.image' => __('Picture'),
        ];
    }
}
