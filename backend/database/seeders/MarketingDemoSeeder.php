<?php

namespace Database\Seeders;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\AffiliateTier;
use App\Models\CustomPage;
use App\Models\StaticPage;
use Illuminate\Database\Seeder;

/** Demo marketing data (formerly GrowthSeeder; affiliates / pages, unrelated to the removed n8n Growth system): commission tiers, static pages, a custom page, an approved affiliate (aff@egy.test / password). */
class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Starter', 0, 5], ['Silver', 1000, 7], ['Gold', 5000, 10]] as [$name, $min, $pct]) {
            AffiliateTier::firstOrCreate(['min_sales' => $min], ['name' => $name, 'percent' => $pct]);
        }
        foreach ([
            'privacy-policy' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy policy'],
            'terms' => ['ar' => 'الشروط والأحكام', 'en' => 'Terms & conditions'],
            'about' => ['ar' => 'من نحن', 'en' => 'About us'],
        ] as $slug => $title) {
            StaticPage::firstOrCreate(['slug' => $slug], ['title' => $title, 'content' => ['ar' => 'محتوى '.$title['ar'], 'en' => 'Content of '.$title['en']], 'is_active' => true]);
        }
        CustomPage::firstOrCreate(['slug' => 'offers'], ['title' => ['ar' => 'العروض', 'en' => 'Offers'], 'filters' => ['flash_sale' => true, 'sort' => 'price_asc'], 'is_active' => true]);

        $a = Affiliate::firstOrCreate(['email' => 'aff@egy.test'], ['name' => 'Demo Affiliate', 'password' => 'password', 'status' => AffiliateStatus::APPROVED, 'approved_at' => now()]);
        $a->forceFill(['status' => AffiliateStatus::APPROVED])->save();
    }
}
