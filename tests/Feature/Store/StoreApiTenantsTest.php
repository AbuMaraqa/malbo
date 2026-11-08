<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Modules\Store\Theme\ThemeService;
use Tests\TenantTestCase;

/**
 * Every store's app gets that store's own look, on its own host.
 */
class StoreApiTenantsTest extends TenantTestCase
{
    protected bool $tenantTransactions = false;

    public function test_each_store_gets_its_own_color(): void
    {
        $this->secondTenant();
        $previous = $this->colors(['testing' => '#6B2D5C', 'other' => '#1D4ED8']);

        try {
            $this->getJson($this->tenantUrl('/api/v1/store/appearance'))->assertOk()->assertJsonPath('data.color', '#6B2D5C');
            $this->getJson('http://other.'.config('tenancy.base_domain').'/api/v1/store/appearance')->assertOk()->assertJsonPath('data.color', '#1D4ED8');
            $this->getJson($this->tenantUrl('/api/v1/store/appearance'))->assertOk()->assertJsonPath('data.color', '#6B2D5C');
        } finally {
            $this->colors($previous);
        }
    }

    /**
     * Sets StoreAppearanceSettings::primary_color per store straight in its database.
     *
     * @param  array<string, string>  $colors  store id => color
     * @return array<string, string> the previous colors
     */
    private function colors(array $colors): array
    {
        $previous = [];

        tenancy()->runForMultiple(array_keys($colors), function () use ($colors, &$previous) {
            $row = DB::table('settings')->where('group', 'store_appearance')->where('name', 'primary_color');
            $previous[tenant('id')] = json_decode($row->value('payload'));
            $row->update(['payload' => json_encode($colors[tenant('id')])]);
            app(ThemeService::class)->forget();
        });

        return $previous;
    }
}
