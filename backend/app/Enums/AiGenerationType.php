<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiGenerationType: string
{
    use HasLabels;

    case SOCIAL_POST = 'social_post';
    case MARKETING_POST = 'marketing_post';
    case BANNER = 'banner';
    case OFFER = 'offer';
    case BUNDLE = 'bundle';
    case PACKAGE = 'package';
    case PRODUCT_EDIT = 'product_edit';
    case TREND_REPORT = 'trend_report';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiGenerationType').'.'.$this->value);
    }
}
