<?php

namespace App\Services\Casting\DTO;

/** Provider-neutral generation request. referencePaths = raw image bytes keyed by role (identity / product); providers without support must refuse, never ignore. */
final readonly class ImageRequest
{
    /** @param array<string,string> $references role => binary image bytes */
    public function __construct(
        public string $prompt,
        public int $width = 832,
        public int $height = 1216,
        public ?int $seed = null,
        public array $references = [],
        public string $negative = '',
    ) {}
}
