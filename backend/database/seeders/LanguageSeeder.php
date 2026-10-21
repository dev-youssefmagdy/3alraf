<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'is_default' => true, 'sort_order' => 1],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => false, 'sort_order' => 2],
        ] as $l) {
            Language::updateOrCreate(['code' => $l['code']], $l + ['is_active' => true]);
        }
    }
}
