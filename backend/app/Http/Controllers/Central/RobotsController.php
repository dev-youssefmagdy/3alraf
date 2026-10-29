<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;

/** Central (platform) host: only the public landing page may be crawled. */
class RobotsController extends Controller
{
    public function __invoke()
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /choose/\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
