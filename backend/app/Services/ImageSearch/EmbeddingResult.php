<?php

namespace App\Services\ImageSearch;

/** One vector from the embedder. $vector = raw little-endian float32 bytes (dim × 4), L2-normalised. */
final readonly class EmbeddingResult
{
    public function __construct(public string $model, public int $dim, public string $vector) {}

    /** @return list<float> */
    public function floats(): array
    {
        return array_values(unpack('g*', $this->vector));
    }
}
