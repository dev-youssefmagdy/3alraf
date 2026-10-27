<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/** Global ISO-3166 country list from database/data/countries.json (idempotent). */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/countries.json')), true) as $c) {
            Country::updateOrCreate(['code' => $c['code']], ['name' => $c['name']]);
        }
    }
}
