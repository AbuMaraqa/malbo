<?php

namespace Tests\Feature\Core;

use Modules\Items\Entities\Product;
use Tests\DatabaseTestCase;
use Tests\Feature\Reports\Concerns\MakesReportData;

/**
 * Filter values are split on commas (config/query-builder.php): a search or text filter typed with a
 * comma ("Shirt, red") reaches its filter as an array. Admin tables search for the whole text.
 */
class TableSearchWithCommaTest extends DatabaseTestCase
{
    use MakesReportData;

    public function test_an_admin_table_search(): void
    {
        $product = Product::factory()->create(['name' => 'Shirt, red', 'sku' => 'COMMA-'.uniqid()]);
        Product::factory()->create(['name' => 'Shirt blue', 'sku' => 'COMMA-B-'.uniqid()]);

        $response = $this->actingAs($this->reportStaff(['Product View List']))
            ->get($this->siteUrl('/en/admin/products?filter[search]='.urlencode('Shirt, red')))
            ->assertOk();

        $this->assertSame([$product->id], array_column($response->viewData('page')['props']['table']['data'], 'id'));
    }

    public function test_an_admin_text_filter(): void
    {
        $this->actingAs($this->reportStaff(['User View List']))
            ->get($this->siteUrl('/ar/admin/users?filter[name]='.urlencode('Staff, one')))
            ->assertOk();
    }

    public function test_a_report_table_and_its_csv(): void
    {
        $staff = $this->reportStaff(['Product Report View']);

        $this->actingAs($staff)->get($this->siteUrl('/ar/admin/reports/products?filter[search]='.urlencode('Shirt, red')))->assertOk();
        $this->actingAs($staff)->get($this->siteUrl('/ar/admin/reports/products/export?filter[search]='.urlencode('Shirt, red')))->assertOk();
    }
}
