<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;

class OfferPagesTest extends DatabaseTestCase
{
    private User $staff;

    private Branch $branch;

    private Category $category;

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

        $this->branch = Branch::factory()->create();
        $this->category = Category::factory()->create();
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $offer = $this->offer(['name' => 'تخفيض', 'code' => 'SAVE10']);

        $this->page('/offers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Offer/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $offer->id)
            ->where('table.data.0.name', 'تخفيض')
            ->where('table.data.0.branch', (string) $this->branch->name)
            ->where('table.data.0.offerable_type', 'items_category')
            ->where('table.data.0.offerable', (string) $this->category->name)
            ->where('table.data.0.type', OfferType::PERCENTAGE->getLabel())
            ->where('table.data.0.is_active', true)
            ->has('types', 3)
            ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_name_and_a_code_in_another_letter_case(): void
    {
        $this->offer(['name' => 'Summer Sale', 'code' => 'HOT']);
        $this->offer(['name' => 'Winter', 'code' => 'COLD']);

        $this->page('/offers?filter[search]=sUMMER')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Summer Sale'));

        $this->page('/offers?filter[search]=cold')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Winter'));
    }

    public function test_each_filter_and_malformed_values(): void
    {
        $other = Branch::factory()->create();
        $product = $this->productIn($other);
        $first = $this->offer(['name' => 'أول', 'type' => OfferType::FIXED, 'is_active' => false, 'code' => 'FIRST', 'value' => 5]);
        $second = $this->offer(['name' => 'ثاني', 'branch_id' => $other->id, 'offerable_type' => Product::class, 'offerable_id' => $product->id, 'value' => 50]);

        $only = fn (string $query, Offer $offer) => $this->page('/offers?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $offer->id));

        $only('filter[name]=أول', $first);
        $only('filter[code]=first', $first);
        $only('filter[type]='.OfferType::FIXED->value, $first);
        $only('filter[is_active]=0', $first);
        $only('filter[branch_id]='.$other->id, $second);
        $only('filter[offerable_model]=items_product', $second);
        $only('filter[value_from]=10', $second);

        $this->page('/offers?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
        $this->page('/offers?filter[type]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
    }

    public function test_sorting(): void
    {
        $this->offer(['name' => 'بقالة', 'value' => 1]);
        $this->offer(['name' => 'أدوات', 'value' => 2]);

        $this->page('/offers?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.name', 'أدوات'));
        $this->page('/offers?sort=-value')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.name', 'أدوات'));
    }

    public function test_add_makes_one_offer_per_branch_with_the_image_on_each(): void
    {
        $second = Branch::factory()->create();

        $this->page('/offers/add')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Offer/Add')
            ->has('submitToken')
            ->has('categories')
            ->has('products'));

        $this->store(['branch_id' => [$this->branch->id, $second->id], 'code' => ''])->assertRedirect($this->url('/offers'))->assertSessionHasNoErrors();

        $offers = Offer::query()->orderBy('id')->get();
        $this->assertCount(2, $offers);
        $this->assertEqualsCanonicalizing([$this->branch->id, $second->id], $offers->pluck('branch_id')->all());

        foreach ($offers as $offer) {
            $this->assertSame('عرض', $offer->translate('ar')->name);
            $this->assertSame(Category::class, $offer->offerable_type);
            $this->assertSame($this->staff->id, (int) $offer->created_by);
            $this->assertTrue($offer->hasMedia('offer'), 'addImage swallows errors, so the media itself is checked');
        }
    }

    public function test_double_submit_does_not_create_duplicates(): void
    {
        $token = (string) Str::uuid();

        $this->store(['submit_token' => $token, 'code' => ''])->assertSessionHasNoErrors();
        $this->store(['submit_token' => $token, 'code' => ''])->assertRedirect($this->url('/offers'));

        $this->assertSame(1, Offer::query()->count());
    }

    public function test_a_refused_form_can_be_sent_again_with_its_token(): void
    {
        $token = (string) Str::uuid();

        $this->store(['submit_token' => $token, 'name' => ''])->assertSessionHasErrors('name');
        $this->store(['submit_token' => $token])->assertSessionHasNoErrors();

        $this->assertSame(1, Offer::query()->count());
    }

    public function test_coupon_rules(): void
    {
        $this->offer(['code' => 'TAKEN']);

        $this->store(['code' => 'TAKEN'])->assertSessionHasErrors('code');
        $this->store(['code' => str_repeat('x', 51)])->assertSessionHasErrors('code');
        $this->store(['type' => OfferType::PERCENTAGE->value, 'value' => 101])->assertSessionHasErrors('value');
        $this->store(['value' => -1])->assertSessionHasErrors('value');
        $this->store(['type' => 99])->assertSessionHasErrors('type');
        $this->store(['usage_limit' => 0])->assertSessionHasErrors('usage_limit');
        $this->store(['usage_limit_per_user' => 'x'])->assertSessionHasErrors('usage_limit_per_user');
        $this->store(['min_purchase_amount' => ''])->assertSessionHasErrors('min_purchase_amount');
        $this->store(['description' => str_repeat('x', 256)])->assertSessionHasErrors('description');
        $this->store(['branch_id' => [99999]])->assertSessionHasErrors('branch_id.0');

        $this->assertSame(1, Offer::query()->count());
    }

    public function test_a_code_with_several_branches_is_refused(): void
    {
        $this->store(['code' => 'ONE', 'branch_id' => [$this->branch->id, Branch::factory()->create()->id]])->assertSessionHasErrors('code');

        $this->assertSame(0, Offer::query()->count());
    }

    public function test_date_window(): void
    {
        $this->store(['start_date' => '2026-11-30T10:00', 'end_date' => '2026-11-01T10:00'])->assertSessionHasErrors('end_date');

        $this->store(['start_date' => '2026-11-01T10:00', 'end_date' => '2026-11-30T10:00'])->assertSessionHasNoErrors();

        $offer = Offer::query()->firstOrFail();
        $this->assertSame('2026-11-01 10:00', $offer->start_date->format('Y-m-d H:i'));
        $this->assertSame('2026-11-30 10:00', $offer->end_date->format('Y-m-d H:i'));
    }

    public function test_a_product_target_must_be_sold_in_the_branch(): void
    {
        $here = $this->productIn($this->branch);
        $elsewhere = $this->productIn(Branch::factory()->create());

        $this->store(['offerable_model' => 'items_product', 'offerable_id' => $elsewhere->id])->assertSessionHasErrors('offerable_id');
        $this->store(['offerable_model' => 'items_category', 'offerable_id' => 999999])->assertSessionHasErrors('offerable_id');
        $this->store(['offerable_model' => 'nonsense'])->assertSessionHasErrors('offerable_model');

        $this->store(['offerable_model' => 'items_product', 'offerable_id' => $here->id])->assertSessionHasNoErrors();

        $offer = Offer::query()->firstOrFail();
        $this->assertSame(Product::class, $offer->offerable_type);
        $this->assertSame($here->id, (int) $offer->offerable_id);
    }

    public function test_the_edit_page_and_update(): void
    {
        $offer = $this->offer(['name' => 'قديم', 'code' => 'KEEP', 'start_date' => '2026-11-01 10:00:00']);
        $offer->addImage(UploadedFile::fake()->image('o.jpg'));

        $this->page('/offers/'.$offer->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Offer/Edit')
            ->where('offer.id', $offer->id)
            ->where('offer.name', 'قديم')
            ->where('offer.offerable_model', 'items_category')
            ->where('offer.start_date', '2026-11-01T10:00')
            ->has('images', 1)
            ->missing('offer.created_by'));

        // The same code stays valid on its own offer; no new image is needed while one is saved.
        $this->update($offer, ['name' => 'جديد', 'code' => 'KEEP', 'value' => 25])->assertRedirect($this->url('/offers'))->assertSessionHasNoErrors();

        $fresh = Offer::query()->findOrFail($offer->id);
        $this->assertSame('جديد', $fresh->translate('ar')->name);
        $this->assertEquals(25, $fresh->value);
        $this->assertTrue($fresh->hasMedia('offer'));
        $this->assertSame(1, Offer::query()->count());
    }

    public function test_removing_the_only_image_needs_a_new_one(): void
    {
        $offer = $this->offer();
        $media = $offer->addImage(UploadedFile::fake()->image('o.jpg'));

        $this->update($offer, ['removed_image' => [$media->id]])->assertSessionHasErrors('image');

        $this->update($offer, ['removed_image' => [$media->id], 'image' => UploadedFile::fake()->image('n.jpg')])->assertSessionHasNoErrors();

        $fresh = Offer::query()->findOrFail($offer->id);
        $this->assertTrue($fresh->hasMedia('offer'));
        $this->assertNotSame($media->id, $fresh->getFirstMedia('offer')->id);
    }

    public function test_translation_comes_from_the_form_not_the_request(): void
    {
        // The removed hook read request() on creation, which also wrote it to the wrong locale.
        app()->setLocale('en');

        $offer = Offer::query()->newModelInstance();
        \Modules\Core\Support\Translations::fill($offer, ['ar' => ['name' => 'صريح']]);
        $offer->fill(['created_by' => $this->staff->id, 'branch_id' => $this->branch->id, 'value' => 1, 'offerable_type' => Category::class, 'offerable_id' => $this->category->id]);
        request()->merge(['name' => 'من الطلب']);
        $offer->save();

        $fresh = Offer::query()->with('translations')->findOrFail($offer->id);
        $this->assertSame('صريح', $fresh->translate('ar')->name);
        $this->assertNull($fresh->translate('en', false));
    }

    public function test_delete_and_bulk_delete(): void
    {
        $one = $this->offer();
        $two = $this->offer();
        $three = $this->offer();

        $this->actingAs($this->staff)->delete($this->url('/offers/'.$one->id))->assertSessionHasNoErrors();
        $this->assertNull(Offer::query()->find($one->id));

        $this->actingAs($this->staff)->delete($this->url('/offers'), ['ids' => [$two->id]])->assertSessionHasNoErrors();
        $this->assertNull(Offer::query()->find($two->id));
        $this->assertNotNull(Offer::query()->find($three->id));
    }

    public function test_info_and_a_bad_id(): void
    {
        $this->actingAs($this->staff)->getJson($this->url('/offers/'.$this->offer()->id.'/info'))->assertOk()->assertJsonStructure(['activities']);
        $this->page('/offers/abc/edit')->assertNotFound();
        $this->page('/offers/999999/edit')->assertNotFound();
    }

    public function test_permission_denied(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@items.test', 'password' => 'secret-password']);
        $viewer->assignRole(Role::findOrCreate('Offer Nothing '.Str::random(5), 'web'));
        $offer = $this->offer();

        $this->actingAs($viewer)->get($this->url('/offers'))->assertForbidden();
        $this->actingAs($viewer)->get($this->url('/offers/add'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/offers/'.$offer->id), [])->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/offers/'.$offer->id))->assertForbidden();
    }

    public function test_the_offers_api_lists_with_filters(): void
    {
        $offer = $this->offer(['name' => 'Summer', 'code' => 'API']);

        $this->getJson($this->siteUrl('/api/v1/items/offers?filter[name]=SUMMER'), ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.0.id', $offer->id);
    }

    private function productIn(Branch $branch): Product
    {
        $product = Product::factory()->create();
        $product->branches()->sync([$branch->id]);

        return $product;
    }

    private function offer(array $attributes = []): Offer
    {
        return Offer::query()->create([
            'name' => 'عرض',
            'branch_id' => $this->branch->id,
            'created_by' => $this->staff->id,
            'type' => OfferType::PERCENTAGE,
            'value' => 10,
            'min_purchase_amount' => 0,
            'usage_limit' => 5,
            'usage_limit_per_user' => 1,
            'is_active' => true,
            'offerable_type' => Category::class,
            'offerable_id' => $this->category->id,
            ...$attributes,
        ]);
    }

    private function fields(array $overrides = []): array
    {
        return [
            'name' => 'عرض',
            'offerable_model' => 'items_category',
            'offerable_id' => $this->category->id,
            'type' => OfferType::PERCENTAGE->value,
            'value' => 10,
            'min_purchase_amount' => 0,
            'usage_limit' => 5,
            'usage_limit_per_user' => 1,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post($this->url('/offers'), $this->fields([
            'submit_token' => (string) Str::uuid(),
            'branch_id' => [$this->branch->id],
            'image' => UploadedFile::fake()->image('offer.jpg'),
            ...$overrides,
        ]));
    }

    private function update(Offer $offer, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->put($this->url('/offers/'.$offer->id), $this->fields(['branch_id' => $this->branch->id, ...$overrides]));
    }

    private function page(string $path): TestResponse
    {
        return $this->actingAs($this->staff)->get($this->url($path));
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin'.$path);
    }
}
