<?php

namespace App\Http\Requests\Marketing\Api;

/**
 * Shared optional filters of the merchandising lists: state (active|live|upcoming|ended|inactive|all), placement (banners),
 * include_content (static pages / blog posts: add the plain-text body), days (seasonal calendar / gift-finder stats).
 */
class ListFilterRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return [
            'state' => ['sometimes', 'in:all,active,live,upcoming,ended,inactive'],
            'placement' => ['sometimes', 'in:home_hero,home_strip,category_tile,popup'],
            'include_content' => ['sometimes', 'boolean'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:366'],
        ];
    }
}
