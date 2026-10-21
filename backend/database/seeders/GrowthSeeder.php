<?php

namespace Database\Seeders;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\AffiliateTier;
use App\Models\CustomPage;
use App\Models\StaticPage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/** Demo data for tenant `egy`: commission tiers, static pages, a custom page, an approved affiliate (aff@egy.test / password). */
class GrowthSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::whereIn('slug', ['egy', 'sa'])->get() as $t) {
            $this->seedTenant($t);
        }
    }

    private function seedTenant(Tenant $t): void
    {
        app()->instance('currentTenant', $t);

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

        $a = Affiliate::firstOrCreate(['email' => "aff@{$t->slug}.test"], ['name' => 'Demo Affiliate', 'password' => 'password', 'status' => AffiliateStatus::APPROVED, 'approved_at' => now()]);
        $a->forceFill(['status' => AffiliateStatus::APPROVED])->save();

        app()->forgetInstance('currentTenant');
    }
}
