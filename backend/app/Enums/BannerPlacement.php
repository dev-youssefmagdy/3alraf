<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum BannerPlacement: string
{
    use HasLabels;

    case HOME_HERO = 'home_hero';
    case HOME_STRIP = 'home_strip';
    case CATEGORY_TILE = 'category_tile';
    case POPUP = 'popup';
}
