<?php

namespace App\Services\Casting\Contracts;

use App\Services\Casting\DTO\GeneratedImage;
use App\Services\Casting\DTO\ImageRequest;

/** What the Casting Studio needs from any image engine (local ComfyUI today; a hosted API can be added without touching the studio). */
interface ImageProvider
{
    public function name(): string;

    /** text_to_image, reference_image (identity / product conditioning), video, identity (prompt_seed|reference_image) */
    public function capabilities(): array;

    /** @return array{ok:bool,message:string} never throws, never leaks URLs with credentials */
    public function health(): array;

    /** @throws \App\Services\Casting\Exceptions\CastingProviderException */
    public function generateImage(ImageRequest $request): GeneratedImage;

    /** @throws \App\Services\Casting\Exceptions\CastingProviderException when video is unsupported / unconfigured */
    public function generateVideo(string $imageBytes, string $prompt, ?int $seed = null, array $options = []): GeneratedImage;
}
