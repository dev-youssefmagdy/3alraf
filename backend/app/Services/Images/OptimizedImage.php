<?php

namespace App\Services\Images;

/** Result of one encode: the bytes plus what it took to get there. `withinLimit=false` means the best attempt still exceeded the profile limit. */
final class OptimizedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly int $width,
        public readonly int $height,
        public readonly int $quality,
        public readonly float $scale,
        public readonly int $limit,
        public readonly string $mime,
        public readonly int $sourceBytes,
        public readonly int $attempts,
    ) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }

    public function withinLimit(): bool
    {
        return $this->size() <= $this->limit;
    }
}
