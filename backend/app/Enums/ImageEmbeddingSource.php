<?php

namespace App\Enums;

/** What an image_embeddings row was computed from: an `images` row (product or variant gallery) or a bundle's single `image` column. */
enum ImageEmbeddingSource: string
{
    case IMAGE = 'image';
    case BUNDLE = 'bundle';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
