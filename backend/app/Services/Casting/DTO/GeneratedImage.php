<?php

namespace App\Services\Casting\DTO;

final readonly class GeneratedImage
{
    public function __construct(public string $bytes, public string $mime, public int $seed, public array $meta = []) {}
}
