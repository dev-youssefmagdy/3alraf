<?php

namespace App\Enums\Marketing;

/** image / video = a private file; post = a social post draft or media brief (caption, storyboard) whose file may come later. */
enum MediaAssetKind: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case POST = 'post';

    public function label(): string
    {
        return __('marketing.media.kinds.'.$this->value);
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
