<?php

namespace Tests\Feature\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Enums\AddonType;
use Tests\DatabaseTestCase;

/**
 * Saving an attribute or an addon wrote the translation of each saved value and option, loading
 * their translations one row at a time (logged as Lazy loading AttributeValue::translations and
 * AddonOption::translations).
 */
class CatalogLazyLoadingTest extends DatabaseTestCase
{
    private User $staff;

    /** @var list<string> */
    private array $lazy = [];

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            $this->lazy[] = $model::class.'::'.$relation;
        });
    }

    public function test_the_attribute_pages_and_saving_its_values(): void
    {
        $attribute = Attribute::factory()->create(['type' => 3]);
        [$one, $two] = AttributeValue::factory()->count(2)->for($attribute)->create()->all();

        $this->actingAs($this->staff)->get($this->url('/attributes'))->assertOk();
        $this->actingAs($this->staff)->get($this->url('/attributes/'.$attribute->id.'/edit'))->assertOk();
        $this->actingAs($this->staff)->put($this->url('/attributes/'.$attribute->id), [
            'name' => 'لون',
            'slug' => 'color-'.Str::lower(Str::random(5)),
            'type' => 3,
            'values' => [['id' => $one->id, 'name' => 'أحمر', 'color_code' => '#ff0000'], ['id' => $two->id, 'name' => 'أزرق', 'color_code' => '#0000ff']],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], array_values(array_unique($this->lazy)));
        $this->assertSame('أحمر', $one->fresh()->translate('ar')->name);
    }

    public function test_the_addon_pages_and_saving_its_options(): void
    {
        $addon = Addon::factory()->create(['type' => AddonType::CHECKBOX]);
        $one = AddonOption::factory()->for($addon)->create();
        $one->addImage(UploadedFile::fake()->image('o.jpg'));

        $this->actingAs($this->staff)->get($this->url('/addons'))->assertOk();
        $this->actingAs($this->staff)->get($this->url('/addons/'.$addon->id.'/edit'))->assertOk();
        $this->actingAs($this->staff)->put($this->url('/addons/'.$addon->id), [
            'name' => 'صوص',
            'type' => AddonType::CHECKBOX->value,
            'options' => [['id' => $one->id, 'name' => 'معدل', 'price' => 3, 'is_default' => true, 'is_quantifiable' => false]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], array_values(array_unique($this->lazy)));
        $this->assertSame('معدل', $one->fresh()->translate('ar')->name);
    }

    public function test_the_catalog_api_lists(): void
    {
        $attribute = Attribute::factory()->create(['type' => 3]);
        AttributeValue::factory()->for($attribute)->create();
        AddonOption::factory()->for(Addon::factory())->create();

        $this->getJson($this->siteUrl('/api/v1/items/attributes'))->assertOk();
        $this->getJson($this->siteUrl('/api/v1/items/addons'))->assertOk();
        // A translated name, which an array or a plain column filter could not do.
        $this->getJson($this->siteUrl('/api/v1/items/attributes?filter[name]=x'))->assertOk();
        $this->getJson($this->siteUrl('/api/v1/items/addons?filter[name]=x'))->assertOk();

        $this->assertSame([], array_values(array_unique($this->lazy)));
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin'.$path);
    }
}
