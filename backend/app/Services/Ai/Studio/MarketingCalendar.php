<?php

namespace App\Services\Ai\Studio;

use Carbon\CarbonImmutable;

/** Seasonal events per country for campaign suggestions. Fixed-date events computed; Islamic dates are approximate windows (documented, the merchant confirms). */
class MarketingCalendar
{
    private const MENA = ['EG', 'SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'JO', 'LB', 'MA', 'DZ', 'TN', 'IQ', 'LY', 'SD', 'YE', 'PS'];

    /** Approximate Gregorian windows of the Islamic events (start dates), by year. */
    private const ISLAMIC = [
        2026 => ['ramadan' => '2026-02-18', 'eid_fitr' => '2026-03-20', 'eid_adha' => '2026-05-27'],
        2027 => ['ramadan' => '2027-02-08', 'eid_fitr' => '2027-03-09', 'eid_adha' => '2027-05-16'],
        2028 => ['ramadan' => '2028-01-28', 'eid_fitr' => '2028-02-26', 'eid_adha' => '2028-05-05'],
    ];

    /** @return array<int,array{key:string,name:string,date:string,days:int}> events within the next $horizon days, nearest first */
    public function upcoming(?string $country, ?CarbonImmutable $from = null, int $horizon = 120): array
    {
        $from ??= CarbonImmutable::now();
        $country = strtoupper((string) $country);
        $out = [];
        foreach ([$from->year, $from->year + 1] as $y) {
            foreach ($this->forYear($y, $country) as $key => $date) {
                $d = CarbonImmutable::parse($date);
                $days = (int) $from->startOfDay()->diffInDays($d->startOfDay(), false);
                if ($days >= 0 && $days <= $horizon) {
                    $out[] = ['key' => $key, 'name' => __('ai.studio.events.'.$key), 'date' => $d->toDateString(), 'days' => $days];
                }
            }
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);

        return $out;
    }

    /** @return array<string,string> */
    public function forYear(int $y, string $country): array
    {
        $e = ['black_friday' => $this->blackFriday($y), 'new_year' => "$y-01-01", 'valentine' => "$y-02-14", 'back_to_school' => "$y-09-01", 'singles_day' => "$y-11-11"];
        if (in_array($country, self::MENA, true) || $country === '') {
            $e += self::ISLAMIC[$y] ?? [];
            $e['white_friday'] = $e['black_friday'];
            $e['mothers_day'] = $country === 'EG' || in_array($country, ['JO', 'LB', 'SY', 'PS', 'IQ', 'KW', 'QA', 'BH', 'OM'], true) ? "$y-03-21" : "$y-05-10";
        }
        if ($country === 'SA') {
            $e['national_day'] = "$y-09-23";
        }
        if ($country === 'AE') {
            $e['national_day'] = "$y-12-02";
        }
        if ($country === 'EG') {
            $e['national_day'] = "$y-07-23";
        }

        return $e;
    }

    private function blackFriday(int $y): string
    {
        return CarbonImmutable::create($y, 11, 1)->modify('fourth friday of this month')->toDateString();
    }

}
