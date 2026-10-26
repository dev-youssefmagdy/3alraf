<?php

namespace App\Support;

/** Static ISO-2 country list for market research: name (en/ar) + local currency. */
final class MarketCountries
{
    public const LIST = [
        'EG' => ['Egypt', 'مصر', 'EGP'], 'SA' => ['Saudi Arabia', 'السعودية', 'SAR'], 'AE' => ['United Arab Emirates', 'الإمارات', 'AED'], 'KW' => ['Kuwait', 'الكويت', 'KWD'],
        'QA' => ['Qatar', 'قطر', 'QAR'], 'BH' => ['Bahrain', 'البحرين', 'BHD'], 'OM' => ['Oman', 'عمان', 'OMR'], 'JO' => ['Jordan', 'الأردن', 'JOD'],
        'LB' => ['Lebanon', 'لبنان', 'LBP'], 'IQ' => ['Iraq', 'العراق', 'IQD'], 'SY' => ['Syria', 'سوريا', 'SYP'], 'PS' => ['Palestine', 'فلسطين', 'ILS'],
        'MA' => ['Morocco', 'المغرب', 'MAD'], 'DZ' => ['Algeria', 'الجزائر', 'DZD'], 'TN' => ['Tunisia', 'تونس', 'TND'], 'LY' => ['Libya', 'ليبيا', 'LYD'],
        'SD' => ['Sudan', 'السودان', 'SDG'], 'YE' => ['Yemen', 'اليمن', 'YER'], 'TR' => ['Turkey', 'تركيا', 'TRY'], 'US' => ['United States', 'الولايات المتحدة', 'USD'],
        'GB' => ['United Kingdom', 'المملكة المتحدة', 'GBP'], 'DE' => ['Germany', 'ألمانيا', 'EUR'], 'FR' => ['France', 'فرنسا', 'EUR'], 'IN' => ['India', 'الهند', 'INR'],
        'CN' => ['China', 'الصين', 'CNY'], 'PK' => ['Pakistan', 'باكستان', 'PKR'],
    ];

    /** @return array<string,string> code => localized name */
    public static function names(?string $locale = null): array
    {
        $i = ($locale ?? app()->getLocale()) === 'ar' ? 1 : 0;

        return array_map(fn ($c) => $c[$i], self::LIST);
    }

    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    public static function currency(string $code): ?string
    {
        return self::LIST[$code][2] ?? null;
    }

    public static function name(string $code): string
    {
        return self::LIST[$code][0] ?? $code;
    }
}
