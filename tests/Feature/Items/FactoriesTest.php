<?php

namespace Tests\Feature\Items;

use Illuminate\Database\Eloquent\Model;
use Modules\Branch\Entities\Branch;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Modules\Items\Enums\ProductStatus;
use Tests\DatabaseTestCase;

class FactoriesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
    }

    public function test_each_factory_creates_a_named_row(): void
    {
        foreach ([Product::class, Category::class, Tag::class, Label::class, Attribute::class, AttributeValue::class, Addon::class, AddonOption::class, Branch::class] as $class) {
            /** @var Model $model */
            $model = $class::factory()->create();

            $this->assertTrue($model->exists, $class);
            $this->assertNotEmpty($model->fresh()->translate('ar')?->name, $class);
        }
    }

    public function test_product_states(): void
    {
        $parent = Product::factory()->draft()->create();
        $variation = Product::factory()->variationOf($parent)->create();

        $this->assertSame(ProductStatus::DRAFT, $parent->fresh()->status);
        $this->assertSame($parent->id, $variation->parent_id);
        $this->assertSame(ProductStatus::DRAFT, $variation->fresh()->status);
        $this->assertSame(ProductStatus::PUBLISHED, Product::factory()->published()->create()->fresh()->status);
    }
}
