<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\SeoSettingsService;

/** `/robots.txt` per tenant host: blocks private/transactional paths, advertises the sitemap (or blocks everything while indexing is off). */
class RobotsController extends Controller
{
    public function __invoke(SeoSettingsService $seo)
    {
        $lines = ['User-agent: *'];
        if (! $seo->current()['indexable']) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/api/', '/webhooks/', '/l/', '/*/cart', '/*/checkout', '/*/account', '/*/order/', '/*/wishlist', '/*/login', '/*/register', '/*/affiliate', '/*/search', '/*/auth/', '/*/payments/'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = 'Allow: /build/';
            $lines[] = 'Allow: /storage/';
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('storefront.sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
