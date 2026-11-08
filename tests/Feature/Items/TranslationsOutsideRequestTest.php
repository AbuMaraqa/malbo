<?php

namespace Tests\Feature\Items;

use Illuminate\Http\Request;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Tests\TenantTestCase;

/**
 * Models never take their translations from the current request (spec §5.3): a console
 * import or a queued job would otherwise store whatever the request holds, or nothing.
 */
class TranslationsOutsideRequestTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
    }

    public function test_models_keep_the_name_they_are_given(): void
    {
        // A request that carries another name, as an unrelated form post would.
        $this->app->instance('request', Request::create('/', 'POST', ['name' => 'WRONG']));

        foreach ([Product::class, Category::class, Tag::class, Label::class, Addon::class, AddonOption::class, Attribute::class, AttributeValue::class] as $class) {
            $model = $class::factory()->create(['name' => 'صحيح']);

            $this->assertSame('صحيح', $model->fresh()->translate('ar')->name, $class);
        }
    }

    public function test_fill_sets_two_locales(): void
    {
        $category = Category::factory()->create(['name' => 'قسم']);

        Translations::fill($category, [
            'ar' => ['name' => 'قسم جديد', 'not_translated' => 'x'],
            'en' => ['name' => 'New category', 'description' => 'D'],
            'fr' => [],
        ]);
        $category->save();

        $fresh = $category->fresh();
        $this->assertSame('قسم جديد', $fresh->translate('ar')->name);
        $this->assertSame('New category', $fresh->translate('en')->name);
        $this->assertSame('D', $fresh->translate('en')->description);
        $this->assertNull($fresh->translate('fr', false));
    }

    public function test_from_form_takes_one_locale(): void
    {
        $this->assertSame(
            ['ar' => ['name' => 'اسم', 'description' => null]],
            Translations::fromForm(['name' => 'اسم', 'slug' => 's'], 'ar', ['name', 'description']),
        );
    }
}
