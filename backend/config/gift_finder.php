<?php

return [
    // Fit score (max 100). Margin is only a tie-breaker, never part of the fit.
    'weights' => [
        'category' => 30,
        'category_neutral' => 15, // no category chosen: everyone gets this
        'recipient' => 25,        // product is explicitly tagged for the chosen recipient
        'occasion' => 20,         // product is tagged for the chosen occasion
        'budget_use' => 15,
        'popularity' => 10,
        'bundle_savings' => 5,    // bundles only: small bonus for what the pack saves vs buying the items separately (full at savings_full_percent)
    ],
    'savings_full_percent' => 30, // a bundle saving this % (or more) of its regular total gets the full bundle_savings bonus
    'popularity_days' => 30,      // popularity = units sold in delivered orders placed in the last N days
    'bucket_size' => 5,           // fit points per tie-break bucket
    'max_results' => 8,
    'max_near_budget' => 3,
    'near_budget_factor' => 1.25, // products up to budget x factor are offered separately, flagged as over budget
    'max_bundles' => 3,           // legacy `bundles` array of the API (best scored bundles)
    'max_items' => 12,            // unified ranked list (`items`: products + bundles)
    'budget_presets' => [250, 500, 1000, 2000, 5000], // storefront quick budget buttons (shop currency)
    'budget_slider_max' => 10000, // storefront budget slider upper end (the number field accepts more)
    'min_price_ratio' => 0.2,     // prefer products costing at least this share of the budget (a 750 kettle is not a "15,000 gift")...
    'min_preferred_results' => 3, // ...unless fewer than this many qualify, then cheaper ones fill in
    'max_restriction_words' => 10,
    'min_restriction_word_length' => 2,
    // GiftExplainer (deterministic): the N best sellers among the results get a "popular" reason.
    'explain_popular_top' => 3,

    // Default of the storefront switch (setting `gift_finder.enabled`, Settings → General). ON unless switched off.
    'enabled_default' => true,

    // Dated occasions for the integration API `gift-finder/occasions` (n8n reminders). Keys = GiftFinderService::OCCASIONS.
    // gregorian: fixed month-day (Egypt / Arab world dates). hijri: [month, day] converted with the Umm al-Qura calendar (ext-intl);
    // lunar dates depend on the moon sighting and can be 1-2 days off, so they are always returned with approximate=true.
    'occasion_calendar' => [
        'valentine' => ['gregorian' => '02-14'],
        'mothers_day' => ['gregorian' => '03-21'],
        'ramadan' => ['hijri' => [9, 1]],
        'eid_fitr' => ['hijri' => [10, 1]],
        'eid_adha' => ['hijri' => [12, 10]],
    ],
    'occasions_default_days' => 45,
    'occasions_max_days' => 366,

    // Tag suggestions (admin button on the Gift Finder tags page). Keyed by English category name; the nearest matching ancestor of a product wins.
    // suitable=false keeps big, impractical or non-gift items out of the finder. Recipients/occasions use the keys of GiftFinderService.
    'suggest' => [
        'defaults' => ['suitable' => true, 'recipients' => [], 'occasions' => []],
        'categories' => [
            'Large Appliances' => ['suitable' => false],
            'Furniture' => ['suitable' => false],
            'Auto Tools' => ['suitable' => false],
            'Cleaning' => ['suitable' => false],
            'Televisions' => ['suitable' => false],
            'Home Appliances' => ['recipients' => ['mother', 'partner', 'friend', 'other'], 'occasions' => ['wedding', 'mothers_day', 'eid_fitr', 'eid_adha', 'ramadan', 'thanks']],
            'Kitchen Tools' => ['recipients' => ['mother', 'partner', 'friend', 'other'], 'occasions' => ['wedding', 'mothers_day', 'eid_fitr', 'eid_adha', 'ramadan', 'thanks']],
            'Home Textiles' => ['recipients' => ['mother', 'partner', 'friend', 'other'], 'occasions' => ['wedding', 'mothers_day', 'eid_fitr', 'eid_adha', 'thanks']],
            'Audio' => ['recipients' => ['partner', 'friend', 'colleague', 'self', 'child'], 'occasions' => ['birthday', 'graduation', 'valentine', 'thanks']],
            'Mobiles' => ['recipients' => ['partner', 'friend', 'self', 'child'], 'occasions' => ['birthday', 'graduation']],
            'Accessories' => ['recipients' => ['partner', 'friend', 'colleague', 'self'], 'occasions' => ['birthday', 'graduation', 'thanks']],
            'Fashion' => ['recipients' => ['mother', 'sister', 'partner', 'friend', 'self'], 'occasions' => ['birthday', 'eid_fitr', 'eid_adha', 'valentine']],
            'Beauty' => ['recipients' => ['mother', 'sister', 'partner', 'friend', 'colleague', 'self'], 'occasions' => ['birthday', 'mothers_day', 'valentine', 'eid_fitr', 'eid_adha', 'thanks']],
        ],
        // a product name containing these narrows the recipients (men's items are not for mother/sister)
        'name_rules' => [
            ['pattern' => '/\b(men|man|mens|men\'s)\b/iu', 'recipients' => ['partner', 'friend', 'colleague', 'self']],
            ['pattern' => '/\b(women|woman|womens|women\'s|ladies|dress)\b/iu', 'recipients' => ['mother', 'sister', 'partner', 'friend', 'colleague', 'self']],
            ['pattern' => '/\b(kids?|child|children|baby)\b/iu', 'recipients' => ['child']],
        ],
        'skip_name_pattern' => '/^(test|demo|sample)\b/iu',
    ],
];
