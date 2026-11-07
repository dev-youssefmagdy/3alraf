<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Seeds the cities of the site country (setting `country`) from database/data/cities/{COUNTRY_CODE}.json (state => cities).
 * Idempotent; existing cities (e.g. the OperationsSeeder demo ones) are matched by English name and kept.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);

        $code = strtoupper((string) setting('country'));
        $file = database_path("data/cities/{$code}.json");
        $country = Country::where('code', $code)->first();
        if (! $country || ! is_file($file)) {
            return;
        }

        $existing = City::query()->get()->keyBy(fn ($c) => $c->getTranslations('name')['en'] ?? '');
        $order = (int) City::query()->max('sort_order') + 1;

        foreach (json_decode(file_get_contents($file), true) as $state) {
            foreach ($state['cities'] as $c) {
                if ($row = $existing->get($c['en'])) {
                    $row->update(['country_id' => $country->id, 'state' => $row->getTranslations('state') ?: $state['state']]);

                    continue;
                }
                $existing[$c['en']] = City::query()->create([
                    'country_id' => $country->id, 'name' => $c, 'state' => $state['state'], 'sort_order' => $order++, 'is_active' => true,
                ]);
            }
        }
    }
}
