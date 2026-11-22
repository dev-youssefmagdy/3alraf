<?php

namespace App\Services\Casting\Providers;

use App\Services\Casting\Contracts\ImageProvider;
use App\Services\Casting\DTO\GeneratedImage;
use App\Services\Casting\DTO\ImageRequest;
use App\Services\Casting\Exceptions\CastingProviderException;

/** Deterministic placeholder images (tests, demos without a GPU). Produces a colour field from the seed: NOT a real portrait. */
class FakeProvider implements ImageProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return config('casting.capabilities.fake');
    }

    public function health(): array
    {
        return ['ok' => true, 'message' => 'fake provider (placeholder images)'];
    }

    public function generateImage(ImageRequest $request): GeneratedImage
    {
        $seed = $request->seed ?? random_int(1, 2_000_000_000);
        $w = min(max($request->width, 64), 256);
        $h = min(max($request->height, 64), 256);
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $seed % 200 + 30, intdiv($seed, 7) % 200 + 30, intdiv($seed, 13) % 200 + 30));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        return new GeneratedImage($bytes, 'image/png', $seed, ['provider' => 'fake']);
    }

    public function generateVideo(string $imageBytes, string $prompt, ?int $seed = null, array $options = []): GeneratedImage
    {
        throw CastingProviderException::unsupported('video');
    }
}
