<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds every tenant's cities from database/data/cities/{COUNTRY_CODE}.json
 * (state => cities) for the tenant's country. Idempotent; existing cities
 * (e.g. the OperationsSeeder demo ones) are matched by English name and kept.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);

        foreach (Tenant::all() as $tenant) {
            $code = strtoupper((string) $tenant->country);
            $file = database_path("data/cities/{$code}.json");
            $country = Country::where('code', $code)->first();
            if (! $country || ! is_file($file)) {
                continue;
            }

            $existing = City::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()
                ->keyBy(fn ($c) => $c->getTranslations('name')['en'] ?? '');
            $order = (int) City::withoutGlobalScopes()->where('tenant_id', $tenant->id)->max('sort_order') + 1;

            foreach (json_decode(file_get_contents($file), true) as $state) {
                foreach ($state['cities'] as $c) {
                    if ($row = $existing->get($c['en'])) {
                        $row->update(['country_id' => $country->id, 'state' => $row->getTranslations('state') ?: $state['state']]);

                        continue;
                    }
                    $existing[$c['en']] = City::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id, 'country_id' => $country->id, 'name' => $c, 'state' => $state['state'],
                        'sort_order' => $order++, 'is_active' => true,
                    ]);
                }
            }
        }
    }
}
